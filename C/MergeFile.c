#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>

#define MIN(X, Y) (((X) < (Y)) ? (X) : (Y))
#define MAX(X, Y) (((X) > (Y)) ? (X) : (Y))
#define NULL_PIXEL ((RGBValues){.r = -1, .g = -1, .b = -1})
#define MAX_REGION_SIZE 16 //current biggest square lego, also most cost efficient.
#define MAX_HOLE_NUMBER 4 // number of the maximum number of holes in the current catalog.

///////////////////////////////////STRUCTURES//////////////////////////////////////

// Making a struct to make color comparison easier
typedef struct {
    int r;
    int g;
    int b;
} RGBValues;

// Making a brick struct that uses the RGBValues struct
typedef struct {
    char name[32];
    int width;
    int height;
    int holes[MAX_HOLE_NUMBER];
    RGBValues color;
    float price;
    int number;
} Brick;

// this will contain most of the unchangeable data the algorytms might require
// i initially used Global variables but changed it to a struct for more consistency
typedef struct {
    RGBValues* pixels;
    int width;
    int height;
    int canvasDims;
} Image;

// same reason as Image
typedef struct {
    Brick* bricks;
    int size;
} Catalog;


// this will be useful to make the tiling as well as calculating its quality
typedef struct {
    int index;
    int diff;
} BestMatch;

// Result summary for one tiling
typedef struct {
    double price_total;
    double quality;
    long pieces;
    int missing;
    const char* out_path;
} PavageResult;

// Accumulator used during tiling
typedef struct {
    double totalPrice;
    double totalDiff;
    long pieces;
} Accum;


// an easier way to store the informations the quadtree needs to access
typedef struct {
    RGBValues averageColor;
    double variance;
} RegionData;

// crucial for the quadtree, its a chained list element in its fundamentals, but it carries additional infos.
typedef struct Node {
    int x; 
    int y;
    int w;
    int h;
    int is_leaf;
    RGBValues avg;
    struct Node* child[4];
} Node;



///////////////////////////////////UTILS FUNCTIONS//////////////////////////////////////

/* a function that returns the closest power of 2 greater than or equal to n.
 * Input: n (integer to check)
 * Output: The computed power of 2. Used for padding the canvas. */
int biggest_pow_2(int n) {

    // this is the method i originally used, but when reviewing my code trying to find
    // a way to optimize my it, i found out about bit shifts, which is a lot faster than math.pow().
    // the idea isn't from me however, which is why i'leaving my original version.
    /*
    int p = 0;
    while (pow(2,p) < n) {
        p++;
    }
    return pow(2,p);
    */

    int p = 1;
    while (p < n){ 
        p <<= 1;
    }
    return p;
}

/* parses a hex string (e.g., "FFFFFF") into an RGB struct.
 * Input: hex string (must be 6 chars).
 * Output: RGBValues struct. Exits program on invalid format. */
RGBValues hex_to_RGB(const char *hex) {
    if (!hex || (int)strlen(hex) != 6) {
        perror("invalid hex format");
        exit(1);
    }
    RGBValues p;
    sscanf(hex, "%02x%02x%02x", &p.r, &p.g, &p.b);
    return p;
}

/* parsers the holes string from the catalog into an integer array.
 * Input: string like "0123" or "-1", and the target int array.
 * Output: Modifies the 'holes' array in place. */
void parse_holes(const char *str, int *holes) {
    if (!str || strlen(str) > MAX_HOLE_NUMBER) {
        perror("there is an error in the catalog format");
        exit(1);
    }
    for (int i = 0; i < MAX_HOLE_NUMBER; i++) holes[i] = -1;
    
    if (strcmp(str, "-1") == 0) return;

    int k = 0;
    for (int i = 0; str[i]; i++) {
        if (str[i] >= '0' && str[i] <= '9') holes[k++] = str[i] - '0';
    }
}

/* calculates the squared euclidean distance between two colors.
 * Input: two RGBValues to compare.
 * Output: integer difference (lower means better match). */
int compare_colors(RGBValues p1, RGBValues p2) {
    return
           (p1.r - p2.r) * (p1.r - p2.r)
         + (p1.g - p2.g) * (p1.g - p2.g)
         + (p1.b - p2.b) * (p1.b - p2.b);
}

/* Calculates average color and variance for a specific rectangular region.
 * Input: Image, starting coords (x,y), and dimensions (w,h) of the block.
 * Output: RegionData struct containing the mean color and variance score. */
RegionData avg_and_var(Image reg, int regX, int regY, int w, int h) {

    RegionData rd;
    double varR = 0, varG = 0, varB = 0;
    double sumR = 0, sumG = 0, sumB = 0;
    int count = 0;

    for(int y = regY; y < regY + h; y++) {
        for(int x = regX; x < regX + w; x++) {
            RGBValues p = reg.pixels[y * reg.canvasDims + x];
            if (p.r < 0) continue;

            sumR += p.r;
            sumG += p.g;
            sumB += p.b;

            varR += p.r * p.r;
            varG += p.g * p.g;
            varB += p.b * p.b;
            count++;
        }
    }

    if (count == 0) {
        rd.averageColor = NULL_PIXEL;
        rd.variance = 0;
        return rd;
    }

    // calculating and assigning the average color
    double avgR = sumR / count;
    double avgG = sumG / count;
    double avgB = sumB / count;
    rd.averageColor.r = (int)avgR;
    rd.averageColor.g = (int)avgG;
    rd.averageColor.b = (int)avgB;

    /*calculating the variance to use it as a metric determining wether we split or not
     *i initially wanted to simply compare the average color to every pixel of the region.
     *but after looking a bit for a better KPI of accuracy, i found that variance was a better option*/
    varR = (varR / count) - (avgR * avgR);
    varG = (varG / count) - (avgG * avgG);
    varB = (varB / count) - (avgB * avgB);

    rd.variance = varR + varG + varB;
    return rd;
}

/* a function that decides if the current Quadtree node needs to be split further.
 * Input: dimensions, calculated RegionData, and user-defined threshold.
 * Output: 1 (true) if we split, 0 (false) if it's a leaf. */
int do_we_split(Image reg, int regX, int regY, int currentW, int currentH, RegionData regD, int thresh) {

    if(regD.averageColor.r < 0) return 0; // if the region is fully NULL, we don't split, it saves a lot of space
    if(regD.variance >= thresh) return 1; // if the variance is above the threshold, we split
    if(currentW > MAX_REGION_SIZE) return 1; // since the biggest and most cost efficient lego tile possible is 16*16, anything bigger than that : WE SPLIT.

    /* if the current quadtree region goes beyond the original image, we split.
     *we ideally don't want this to happen because it makes the edges of the image very ugly and cost inefficient
     *to prevent that, we will recommand to the user to make their images dimensions a multiple of 16.*/
    if(regX + currentW > reg.width || regY + currentH > reg.height) return 1;
    
    return 0;
}


/* creates and initializes a new Quadtree Node.
 * Input: geometry (x,y,w,h), leaf status, and average color.
 * Output: Pointer to the new Node with children set to NULL. */
Node* make_new_node(int x, int y, int w, int h, int is_leaf, RGBValues avg) {
    Node* n;
    if (n = malloc(sizeof(Node))) {
        n->x = x;
        n->y = y;
        n->w = w;
        n->h = h;
        n->is_leaf = is_leaf;
        n->avg = avg;
    } else {
        perror("malloc for node failed");
        exit(1);
    }

    // note that a Node closely resembles the "liste chainée we've seen in class"
    for (int i = 0; i < 4; i++)
        n->child[i] = NULL;

    return n;
}

/* Debug function: Prints the pixel values of the entire canvas to console.
 * Input: Image struct.
 * Output: void. */
void show_canvas(Image img) {
    for (int y = 0; y < img.canvasDims; y++) {
        for (int x = 0; x < img.canvasDims; x++) {
            RGBValues c = img.pixels[y * img.canvasDims + x];

            if (c.r < 0) {
                printf("NULL    ");
            } else {
                printf("#%02X%02X%02X ", c.r, c.g, c.b);
            }
        }
        printf("\n");
    }
}

/* Recursively frees memory for the entire Quadtree.
 * Input: Root node of the tree.
 * Output: void. */
void free_QUADTREE(Node* node) {
    if (!node) return;
    for(int i = 0; i < 4; i++) {
        free_QUADTREE(node->child[i]);
    }
    free(node);
}

/* Writes a complete invoice based on the missing bricks in a given catalog into a CSV file
 * Input: Catalog to analyze and the name of the file we'll write the invoice in.
 * Output: Void. invoice file created.*/
void make_order_file(Catalog catalog, const char* name) {
    FILE* f = fopen(name, "w");
    if (!f) {
        perror("Unable to create the invoice");
        exit(1);
    }

    for (int i = 0; i < catalog.size; i++) {
        Brick b = catalog.bricks[i];
        
        // if it's negative, then we are missing bricks
        int missing = 0;
        if(b.number < 0) missing = -b.number;
        
        // only write lines where stock changed or is missing
        if (missing > 0) {
            fprintf(f, "%s,%d\n", b.name, missing);
        }
    }
    fclose(f);
    printf("Invoice generated: %s\n", name);
}

///////////////////////////////////FORMATTING FUNCTIONS//////////////////////////////////////


/* Reads the image file, creates a canvas to pad the image to the next power of 2, and fills the struct.
 * Input: file path to the image text file.
 * Output: A fully populated Image struct. Exits on file error. */
Image load_image(const char* path) {

    char hex[7]; // this is the format of a "pixel" in the initial file
    Image output;

    // we open the file
    FILE* image = fopen(path, "r");
    if (!image) {
        perror("Error opening file");
        exit(1);
    }

    // we read the dimensions of the image
    int w, h;
    if (fscanf(image, "%d %d\n", &w, &h) != 2) {
        fprintf(stderr, "Invalid image file: missing dimensions\n");
        exit(1);
    }

    //write the dimension into the output
    output.width = w;
    output.height = h;
    output.canvasDims = biggest_pow_2(MAX(w,h));

    //we setup the containers of our image
    RGBValues* temp = malloc(output.width * output.height * sizeof(RGBValues));
    RGBValues* pixels = calloc((output.canvasDims * output.canvasDims), sizeof(RGBValues));
    if (!temp || !pixels) {
        perror("Memory allocation failed");
        fclose(image);
        exit(1);
    }

    // we first transfer the image into temp
    int i = 0;
    while (fscanf(image, "%6s", hex) == 1 && i < output.width * output.height) {
        temp[i++] = hex_to_RGB(hex);
    }

    // we fill the canvas with nuill pixels to prepare it for the quadtree
    for (int i = 0; i < output.canvasDims * output.canvasDims; i++){
        pixels[i] = NULL_PIXEL;
    }

    // we transfer temp into the canvas
    for (int y = 0; y < output.height; y++) {
        for (int x = 0; x < output.width; x++) {
            pixels[y * output.canvasDims + x] = temp[y * output.width + x];
        }
    }

    //showCanvas(pixels);  //if you wish to visualize the result.

    // Closing the file and freeing the memory
    free(temp);
    fclose(image);
    output.pixels = pixels;
    return output;
}

/* parses the catalog file and fills the Catalog struct with Brick data.
 * Input: file path to the catalog text file.
 * Output: A Catalog struct containing the array of Bricks and the total count. */
Catalog load_catalog(const char *path) {

    Catalog output;
    output.bricks = NULL;
    output.size = 0;

    FILE* cat = fopen(path, "r");
    if (!cat) {
        perror("Error opening catalog file");
        exit(1);
    }

    // read the number of lines
    int n;
    if (fscanf(cat, "%d\n", &n) != 1) {
        fprintf(stderr, "Invalid catalog file: missing line count\n");
        exit(1);
    }

    //allocate the space
    output.size = n;
    output.bricks = malloc(n * sizeof(Brick));
    if (!output.bricks) {
        perror("Memory allocation failed");
        exit(1);
    }

    //scans the file following a specific format the Java part follows as well
    for (int i = 0; i < n; i++) {
        int w, h, stock;
        char holesStr[16];
        char hex[16];
        float price;

        if (fscanf(cat, "%d,%d,%15[^,],%15[^,],%f,%d",
                   &w, &h, holesStr, hex, &price, &stock) != 6) {
            fprintf(stderr, "Invalid line %d in catalog\n", i + 1);
            exit(1);
        }

        output.bricks[i].width  = w;
        output.bricks[i].height = h;
        output.bricks[i].price  = price;
        output.bricks[i].number = stock;
        output.bricks[i].color  = hex_to_RGB(hex);
        parse_holes(holesStr, output.bricks[i].holes);

        //writes the name of the brick wollowing the format required by the JAVA part
        snprintf(output.bricks[i].name, sizeof(output.bricks[i].name), "%d-%d/%s", w, h, hex);
    }

    fclose(cat);
    return output;
}



///////////////////////////////////TILING ALGORYTHMS//////////////////////////////////////


/* runs through the catalog to find the brick with the closest color match.
 * and the same dimensions. 
 *
 * IMPORTANT NOTE : I could've made the choice to restrain the catalog to only the bricks AVAILABLE,
 * but i chose to send back the full, perfect tiling, and give the stock status to the JAVA part.
 * Java teacher was Okay with it.
 * 
 * Input: target color, target dimensions (w,h), and the catalog.
 * Output: BestMatch struct containing the index of the best brick and the diff score. */
/* Finds the best brick match for a given target color and size.
 * If enforce_stock=1, only considers bricks with stock > 0.
 * The choice minimizes: score = w_diff * diff_norm + w_price * price_norm
 * where diff_norm is in [0,1] and price_norm is in [0,1] (normalized by max catalog price).
 */
BestMatch find_best_match(RGBValues color, int w, int h, Catalog catalog,
                          int enforce_stock, double w_diff, double w_price, double max_price) {

    int bestIndex = -1;
    int bestDiff = -1;
    double bestScore = 0.0;

    if (max_price <= 0.0) max_price = 1.0;

    for (int i = 0; i < catalog.size; i++) {
        Brick current = catalog.bricks[i];

        // must match size
        if (current.width != w || current.height != h) continue;

        // optional stock constraint
        if (enforce_stock && current.number <= 0) continue;

        int diff = compare_colors(color, current.color);

        // normalize
        double diff_norm = (double)diff / (255.0 * 255.0 * 3.0);
        double price_norm = (double)current.price / max_price;

        double score = w_diff * diff_norm + w_price * price_norm;

        if (bestIndex == -1 || score < bestScore) {
            bestIndex = i;
            bestDiff = diff;
            bestScore = score;
        }
    }

    BestMatch result = {bestIndex, bestDiff};
    return result;
}



/* Generates a 1x1 tiling of the image, writes to file, and prints stats.
 * After some testing, it appears to be slower than the quadtree.
 * Input: Image, Catalog, and the desired output filename.
 * Output: void (writes file and prints accuracy/price/missing stock). */
PavageResult toBrick_1x1(Image image, Catalog catalog, const char* out_path,
                          int enforce_stock, double w_diff, double w_price, double max_price) {
    Accum acc = {0.0, 0.0, 0};

    FILE* tiled = fopen(out_path, "w");
    if (!tiled) {
        perror("Error opening output file");
        PavageResult err = {0.0, 0.0, 0, 0, out_path};
        return err;
    }

    for (int y = 0; y < image.height; y++) {
        for (int x = 0; x < image.width; x++) {
            RGBValues px = image.pixels[y * image.width + x];

            BestMatch best = find_best_match(px, 1, 1, catalog, enforce_stock, w_diff, w_price, max_price);
            if (best.index < 0 && enforce_stock) {
                // fallback (should not happen if at least one 1x1 exists)
                best = find_best_match(px, 1, 1, catalog, 0, w_diff, w_price, max_price);
            }
            if (best.index < 0) continue; // no matching 1x1 in catalog

            int id = best.index;

            acc.totalPrice += catalog.bricks[id].price;
            acc.totalDiff += (double)best.diff;
            acc.pieces += 1;

            // mutate stock counters
            catalog.bricks[id].number--;

            // output line: piece_id/name, x, y, rotation
            fprintf(tiled, "%s,%d,%d,%d\n", catalog.bricks[id].name, x, y, 0);
        }
    }

    fclose(tiled);

    // quality in %
    double maxDiff = (255.0 * 255.0 * 3.0) * (image.width * image.height);
    double quality = 100.0;
    if (maxDiff > 0.0) quality = (1.0 - (acc.totalDiff / maxDiff)) * 100.0;

    // missing bricks count
    int missingTotal = 0;
    for (int i = 0; i < catalog.size; i++) {
        if (catalog.bricks[i].number < 0) missingTotal += -catalog.bricks[i].number;
    }

    PavageResult res;
    res.price_total = acc.totalPrice;
    res.quality = quality;
    res.pieces = acc.pieces;
    res.missing = missingTotal;
    res.out_path = out_path;
    return res;
}


/* The core recursive function. Splits regions based on variance and matches bricks to leaves.
 * Input: Image, current recursion coords (x,y,w,h), threshold, file pointer, catalog.
 * Output: Returns the current Node* (links children to parents). */
Node* QUADTREE_RAW(Image image, int x, int y, int w, int h, int thresh, FILE* outFile, Catalog catalog,
                 Accum* acc, int enforce_stock, double w_diff, double w_price, double max_price) {

    RegionData rd = avg_and_var(image, x, y, w, h);

    if (do_we_split(image, x, y, w, h, rd, thresh)) {
        Node* node = make_new_node(x, y, w, h, 0, rd.averageColor);

        int halfW = w / 2;
        int halfH = h / 2;

        node->child[0] = QUADTREE_RAW(image, x,       y,       halfW, halfH, thresh, outFile, catalog, acc, enforce_stock, w_diff, w_price, max_price);
        node->child[1] = QUADTREE_RAW(image, x+halfW, y,       halfW, halfH, thresh, outFile, catalog, acc, enforce_stock, w_diff, w_price, max_price);
        node->child[2] = QUADTREE_RAW(image, x,       y+halfH, halfW, halfH, thresh, outFile, catalog, acc, enforce_stock, w_diff, w_price, max_price);
        node->child[3] = QUADTREE_RAW(image, x+halfW, y+halfH, halfW, halfH, thresh, outFile, catalog, acc, enforce_stock, w_diff, w_price, max_price);

        return node;
    }

    Node* node = make_new_node(x, y, w, h, 1, rd.averageColor);

    // skip padded-only regions
    if (rd.averageColor.r < 0) return node;

    BestMatch best = find_best_match(rd.averageColor, w, h, catalog, enforce_stock, w_diff, w_price, max_price);
    if (best.index < 0 && enforce_stock) {
        best = find_best_match(rd.averageColor, w, h, catalog, 0, w_diff, w_price, max_price);
    }
    if (best.index < 0) return node;

    int id = best.index;

    if (acc) {
        acc->totalPrice += catalog.bricks[id].price;
        acc->pieces += 1;
        acc->totalDiff += (double)best.diff * (double)(w * h);
    }

    catalog.bricks[id].number--;

    fprintf(outFile, "%s,%d,%d,%d\n", catalog.bricks[id].name, x, y, 0);

    return node;
}


/*A capsule function that runs the Quadtree algorithm, handles file I/O, and reports stats.
 * Input: Image, Catalog, output filename, and variance threshold.
 * Output: The root Node of the generated tree. */
PavageResult tobrick_QUADTREE(Image img, Catalog catalog, const char* out_path, int threshold,
                             int enforce_stock, double w_diff, double w_price, double max_price) {

    Accum acc = {0.0, 0.0, 0};

    FILE* outFile = fopen(out_path, "w");
    if (!outFile) {
        perror("Failed to open output file");
        PavageResult err = {0.0, 0.0, 0, 0, out_path};
        return err;
    }

    Node* root = QUADTREE_RAW(img, 0, 0, img.canvasDims, img.canvasDims, threshold, outFile, catalog,
                             &acc, enforce_stock, w_diff, w_price, max_price);

    fclose(outFile);

    // Missing bricks count
    int missingTotal = 0;
    for (int i = 0; i < catalog.size; i++) {
        if (catalog.bricks[i].number < 0) missingTotal += -catalog.bricks[i].number;
    }

    // quality in %
    double maxDiff = (255.0 * 255.0 * 3.0) * (img.width * img.height);
    double quality = 100.0;
    if (maxDiff > 0.0) quality = (1.0 - (acc.totalDiff / maxDiff)) * 100.0;

    PavageResult res;
    res.price_total = acc.totalPrice;
    res.quality = quality;
    res.pieces = acc.pieces;
    res.missing = missingTotal;
    res.out_path = out_path;

    free_QUADTREE(root);

    return res;
}



/////////////////////// MAIN FUNCTION //////////////////////////

int main(int argc, char *argv[]) {
    // V4 usage: ./pavage_v4 <image_file> <catalog_file> <threshold> [output_prefix]
    if (argc != 4 && argc != 5) {
        fprintf(stderr, "Usage: %s <image_file> <catalog_file> <threshold> [output_prefix]\n", argv[0]);
        return 1;
    }

    const char* imagePath = argv[1];
    const char* catalogPath = argv[2];
    int threshold = atoi(argv[3]);
    const char* prefix = (argc == 5) ? argv[4] : "out";

    Catalog catBase = load_catalog(catalogPath);
    if (catBase.size <= 0 || catBase.bricks == NULL) {
        fprintf(stderr, "Failed to load catalog: %s\n", catalogPath);
        return 1;
    }
    Image img = load_image(imagePath);

    double max_price = 0.0;
    for (int i = 0; i < catBase.size; i++) {
        if (catBase.bricks[i].price > max_price) max_price = catBase.bricks[i].price;
    }
    if (max_price <= 0.0) max_price = 1.0;

    Catalog cat_qt_stock = load_catalog(catalogPath);
    Catalog cat_qt_nostock = load_catalog(catalogPath);
    Catalog cat_1x1_price = load_catalog(catalogPath);
    Catalog cat_1x1_stock = load_catalog(catalogPath);

    char out1[256], out2[256], out3[256], out4[256];
    snprintf(out1, sizeof(out1), "%s_quadtree_stock.txt", prefix);
    snprintf(out2, sizeof(out2), "%s_quadtree_nostock.txt", prefix);
    snprintf(out3, sizeof(out3), "%s_1x1_price.txt", prefix);
    snprintf(out4, sizeof(out4), "%s_1x1_stock_quality.txt", prefix);

    PavageResult r1 = tobrick_QUADTREE(img, cat_qt_stock, out1, threshold,
                                      1, 1.0, 0.05, max_price);

    PavageResult r2 = tobrick_QUADTREE(img, cat_qt_nostock, out2, threshold,
                                      0, 1.0, 0.05, max_price);

    PavageResult r3 = toBrick_1x1(img, cat_1x1_price, out3,
                                 0, 0.25, 1.0, max_price);

    PavageResult r4 = toBrick_1x1(img, cat_1x1_stock, out4,
                                 1, 1.0, 0.05, max_price);

    // Stdout: exactly one line per pavage: <output_path> <price> <quality> <missing>
    printf("%s %.2f %.2f %d\n", r1.out_path, r1.price_total, r1.quality, r1.missing);
    printf("%s %.2f %.2f %d\n", r2.out_path, r2.price_total, r2.quality, r2.missing);
    printf("%s %.2f %.2f %d\n", r3.out_path, r3.price_total, r3.quality, r3.missing);
    printf("%s %.2f %.2f %d\n", r4.out_path, r4.price_total, r4.quality, r4.missing);

    // Optional order files (no stdout)
    make_order_file(cat_qt_nostock, "order_quadtree_nostock.txt");
    make_order_file(cat_1x1_price, "order_1x1_price.txt");

    free(img.pixels);
    free(catBase.bricks);
    free(cat_qt_stock.bricks);
    free(cat_qt_nostock.bricks);
    free(cat_1x1_price.bricks);
    free(cat_1x1_stock.bricks);

    return 0;
}
