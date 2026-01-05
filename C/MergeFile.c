#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>
#include <ctype.h>
#include <limits.h>

#define MIN(X, Y) (((X) < (Y)) ? (X) : (Y))
#define MAX(X, Y) (((X) > (Y)) ? (X) : (Y))
#define NULL_PIXEL ((RGBValues){.r = -1, .g = -1, .b = -1})
#define MAX_REGION_SIZE 16 //current biggest square lego, also most cost efficient.
#define MAX_HOLE_NUMBER 4 // number of the maximum number of holes in the current catalog.
#define PRICE_QUALITY_TOL_PCT 10

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
    int stock;
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
    long diff;
} BestMatch;

// an easier way to store the informations the quadtree needs to access
typedef struct {
    RGBValues averageColor;
    double variance;
    int count;
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
//a struct to make the placement easier to use containing the coordinates and rotation
typedef struct {
    char brick_name[32];
    int x;
    int y;
    int rot;
} Placement;
//struct that will be used in the output to showcase details about the output
typedef struct {
    char name[64];
    Placement* placements;
    int count;
    int capacity;
    long price_cents;
    long quality;
    int stock_breaks;
} Solution;

enum {
    STOCK_RELAX = 0,
    STOCK_STRICT = 1
};

enum {
    MATCH_COLOR = 0,
    MATCH_PRICE_BIAS = 1
};



///////////////////////////////////UTILS FUNCTIONS//////////////////////////////////////

static int catalog_has_piece(const Catalog* catalog, int w, int h);
static int catalog_has_piece_in_stock(const Catalog* catalog, int w, int h);
static BestMatch find_best_match_price_bias(RGBValues color, int w, int h, const Catalog* catalog, int stock_mode, int tol_pct);
static int region_can_place(const Image* image, const unsigned char* covered, int x, int y, int w, int h);
static void mark_region(unsigned char* covered, const Image* image, int x, int y, int w, int h);

/* a function that returns the closest power of 2 greater than or equal to n.
 * Input: n (integer to check)
 * Output: The computed power of 2. Used for padding the canvas. */
int biggest_pow_2(int n) {
    int p = 1;
    if (n <= 1) return 1;
    while (p < n){ 
        p <<= 1;
    }
    return p;
}

/* parses a hex string (e.g., "FFFFFF") into an RGB struct.
 * Input: hex string (must be 6 chars).
 * Output: RGBValues struct. Exits program on invalid format. */
int hex_to_RGB(const char *hex, RGBValues* out) {
    const char* s = hex;
    if (!hex || !out) return 0;
    if (hex[0] == '#') s = hex + 1;
    if ((int)strlen(s) != 6) return 0;
    for (int i = 0; i < 6; i++) {
        if (!isxdigit((unsigned char)s[i])) return 0;
    }
    if (sscanf(s, "%02x%02x%02x", &out->r, &out->g, &out->b) != 3) return 0;
    return 1;
}

/* parsers the holes string from the catalog into an integer array.
 * Input: string like "0123" or "-1", and the target int array.
 * Output: Modifies the 'holes' array in place. */
int parse_holes(const char *str, int *holes) {
    if (!str || !holes) return 0;
    if (strlen(str) > MAX_HOLE_NUMBER && strcmp(str, "-1") != 0) return 0;
    for (int i = 0; i < MAX_HOLE_NUMBER; i++) holes[i] = -1;

    if (strcmp(str, "-1") == 0) return 1;
    if (str[0] == '\0') return 0;

    int k = 0;
    for (int i = 0; str[i]; i++) {
        if (str[i] < '0' || str[i] > '9') return 0;
        if (k >= MAX_HOLE_NUMBER) return 0;
        holes[k++] = str[i] - '0';
    }
    return 1;
}

/* calculates the squared euclidean distance between two colors.
 * Input: two RGBValues to compare.
 * Output: integer difference (lower means better match). */
long color_dist2(RGBValues p1, RGBValues p2) {
    return
           (long)(p1.r - p2.r) * (p1.r - p2.r)
         + (long)(p1.g - p2.g) * (p1.g - p2.g)
         + (long)(p1.b - p2.b) * (p1.b - p2.b);
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
        rd.count = 0;
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
    rd.count = count;
    return rd;
}

/* a function that decides if the current Quadtree node needs to be split further.
 * Input: dimensions, calculated RegionData, and user-defined threshold.
 * Output: 1 (true) if we split, 0 (false) if it's a leaf. */
int do_we_split(Image reg, int regX, int regY, int currentW, int currentH, RegionData regD, int thresh, const Catalog* catalog, int stock_mode) {

    if (currentW <= 1 && currentH <= 1) return 0;
    if (regD.count == 0) return 0; // fully NULL region, keep as leaf
    if (stock_mode == STOCK_STRICT) {
        if (!catalog_has_piece_in_stock(catalog, currentW, currentH)) return 1;
    } else {
        if (!catalog_has_piece(catalog, currentW, currentH)) return 1;
    }
    if(regD.variance >= thresh) return 1; // if the variance is above the threshold, we split
    if(currentW > MAX_REGION_SIZE || currentH > MAX_REGION_SIZE) return 1;

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
#ifdef DEBUG
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
#endif
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

static long price_to_cents(float price) {
    return (long)lround(price * 100.0f);
}

static int catalog_has_piece(const Catalog* catalog, int w, int h) {
    for (int i = 0; i < catalog->size; i++) {
        if (catalog->bricks[i].width == w && catalog->bricks[i].height == h) return 1;
    }
    return 0;
}

static int catalog_has_piece_in_stock(const Catalog* catalog, int w, int h) {
    for (int i = 0; i < catalog->size; i++) {
        if (catalog->bricks[i].width == w && catalog->bricks[i].height == h && catalog->bricks[i].stock > 0) return 1;
    }
    return 0;
}

static int catalog_clone(const Catalog* in, Catalog* out) {
    out->size = in->size;
    out->bricks = malloc(sizeof(Brick) * in->size);
    if (!out->bricks) return 0;
    memcpy(out->bricks, in->bricks, sizeof(Brick) * in->size);
    return 1;
}

static int consume_stock(Catalog* catalog, int idx, int strict, int* breaks) {
    if (idx < 0) return 0;
    if (strict) {
        if (catalog->bricks[idx].stock <= 0) return 0;
        catalog->bricks[idx].stock--;
        return 1;
    }
    if (catalog->bricks[idx].stock <= 0 && breaks) (*breaks)++;
    catalog->bricks[idx].stock--;
    return 1;
}

static void solution_init(Solution* sol, const char* name) {
    if (!sol) return;
    sol->placements = NULL;
    sol->count = 0;
    sol->capacity = 0;
    sol->price_cents = 0;
    sol->quality = 0;
    sol->stock_breaks = 0;
    if (name) {
        snprintf(sol->name, sizeof(sol->name), "%s", name);
    } else {
        sol->name[0] = '\0';
    }
}

static int solution_push(Solution* sol, const char* brick_name, int x, int y, int rot) {
    if (!sol || !brick_name) return 0;
    if (sol->count >= sol->capacity) {
        int new_cap = sol->capacity == 0 ? 128 : sol->capacity * 2;
        Placement* next = realloc(sol->placements, sizeof(Placement) * new_cap);
        if (!next) return 0;
        sol->placements = next;
        sol->capacity = new_cap;
    }
    snprintf(sol->placements[sol->count].brick_name, sizeof(sol->placements[sol->count].brick_name), "%s", brick_name);
    sol->placements[sol->count].x = x;
    sol->placements[sol->count].y = y;
    sol->placements[sol->count].rot = rot;
    sol->count++;
    return 1;
}

static void solution_free(Solution* sol) {
    if (!sol) return;
    free(sol->placements);
    sol->placements = NULL;
    sol->count = 0;
    sol->capacity = 0;
}

static int write_solution_file(const Solution* sol, const char* path) {
    FILE* f = fopen(path, "w");
    if (!f) return 0;
    for (int i = 0; i < sol->count; i++) {
        fprintf(f, "%s,%d,%d\n", sol->placements[i].brick_name, sol->placements[i].x, sol->placements[i].y);
    }
    fclose(f);
    return 1;
}

static long piece_error_region(const Image* img, int x, int y, int w, int h, RGBValues color) {
    long total = 0;
    for (int iy = y; iy < y + h; iy++) {
        for (int ix = x; ix < x + w; ix++) {
            RGBValues p = img->pixels[iy * img->canvasDims + ix];
            if (p.r < 0) continue;
            total += color_dist2(p, color);
        }
    }
    return total;
}

static int region_can_place(const Image* image, const unsigned char* covered, int x, int y, int w, int h) {
    if (x < 0 || y < 0) return 0;
    if (x + w > image->width || y + h > image->height) return 0;
    for (int iy = y; iy < y + h; iy++) {
        for (int ix = x; ix < x + w; ix++) {
            int idx = iy * image->canvasDims + ix;
            if (covered[idx]) return 0;
            if (image->pixels[idx].r < 0) return 0;
        }
    }
    return 1;
}

static void mark_region(unsigned char* covered, const Image* image, int x, int y, int w, int h) {
    for (int iy = y; iy < y + h; iy++) {
        for (int ix = x; ix < x + w; ix++) {
            covered[iy * image->canvasDims + ix] = 1;
        }
    }
}

/* Writes a complete invoice based on the missing bricks in a given catalog into a CSV file
 * Input: Catalog to analyze and the name of the file we'll write the invoice in.
 * Output: Void. invoice file created.*/
int write_stock_report(Catalog catalog, const char* name) {
    FILE* f = fopen(name, "w");
    if (!f) {
        perror("Unable to create the invoice");
        return 0;
    }

    int missing_total = 0;
    for (int i = 0; i < catalog.size; i++) {
        Brick b = catalog.bricks[i];
        int missing = 0;
        if (b.stock < 0) missing = -b.stock;
        if (missing > 0) {
            fprintf(f, "%s,%d\n", b.name, missing);
            missing_total += missing;
        }
    }
    if (missing_total == 0) {
        fprintf(f, "0 missing\n");
    }
    fclose(f);
    return 1;
}

static void build_path(char* out, size_t out_size, const char* dir, const char* file) {
    if (!dir || strcmp(dir, ".") == 0 || dir[0] == '\0') {
        snprintf(out, out_size, "%s", file);
        return;
    }
    size_t len = strlen(dir);
    if (dir[len - 1] == '/' || dir[len - 1] == '\\') {
        snprintf(out, out_size, "%s%s", dir, file);
    } else {
        snprintf(out, out_size, "%s/%s", dir, file);
    }
}

static void print_solution_summary_stdout(const char* filename, const Solution* sol) {
    printf("%s %ld %ld %d\n", filename, sol->price_cents, sol->quality, sol->stock_breaks);
}

///////////////////////////////////FORMATTING FUNCTIONS//////////////////////////////////////


/* Reads the image file, creates a canvas to pad the image to the next power of 2, and fills the struct.
 * Input: file path to the image text file.
 * Output: A fully populated Image struct. Exits on file error. */
int load_image(const char* path, Image* out) {

    char hex[8]; // supports "RRGGBB" or "#RRGGBB"
    Image output;

    // we open the file
    FILE* image = fopen(path, "r");
    if (!image) {
        perror("Error opening file");
        return 0;
    }

    // we read the dimensions of the image
    int w, h;
    if (fscanf(image, "%d %d\n", &w, &h) != 2) {
        fprintf(stderr, "Invalid image file: missing dimensions\n");
        fclose(image);
        return 0;
    }
    if (w <= 0 || h <= 0) {
        fprintf(stderr, "Invalid image file: non-positive dimensions\n");
        fclose(image);
        return 0;
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
        free(temp);
        free(pixels);
        return 0;
    }

    // we first transfer the image into temp
    int total = output.width * output.height;
    for (int i = 0; i < total; i++) {
        RGBValues parsed;
        if (fscanf(image, "%7s", hex) != 1) {
            fprintf(stderr, "Invalid image file: not enough pixels\n");
            free(temp);
            free(pixels);
            fclose(image);
            return 0;
        }
        if (!hex_to_RGB(hex, &parsed)) {
            fprintf(stderr, "Invalid image file: bad hex pixel\n");
            free(temp);
            free(pixels);
            fclose(image);
            return 0;
        }
        temp[i] = parsed;
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
    *out = output;
    return 1;
}

/* parses the catalog file and fills the Catalog struct with Brick data.
 * Input: file path to the catalog text file.
 * Output: A Catalog struct containing the array of Bricks and the total count. */
int load_catalog(const char *path, Catalog* out) {

    Catalog output;
    output.bricks = NULL;
    output.size = 0;

    FILE* cat = fopen(path, "r");
    if (!cat) {
        perror("Error opening catalog file");
        return 0;
    }

    // read the number of lines
    int n;
    if (fscanf(cat, "%d\n", &n) != 1) {
        fprintf(stderr, "Invalid catalog file: missing line count\n");
        fclose(cat);
        return 0;
    }

    //allocate the space
    output.size = n;
    output.bricks = malloc(n * sizeof(Brick));
    if (!output.bricks) {
        perror("Memory allocation failed");
        fclose(cat);
        return 0;
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
            free(output.bricks);
            fclose(cat);
            return 0;
        }

        output.bricks[i].width  = w;
        output.bricks[i].height = h;
        output.bricks[i].price  = price;
        output.bricks[i].stock  = stock;
        if (!hex_to_RGB(hex, &output.bricks[i].color)) {
            fprintf(stderr, "Invalid color in catalog line %d\n", i + 1);
            free(output.bricks);
            fclose(cat);
            return 0;
        }
        if (!parse_holes(holesStr, output.bricks[i].holes)) {
            fprintf(stderr, "Invalid holes in catalog line %d\n", i + 1);
            free(output.bricks);
            fclose(cat);
            return 0;
        }

        //writes the name of the brick wollowing the format required by the JAVA part
        snprintf(output.bricks[i].name, sizeof(output.bricks[i].name), "%d-%d/%s", w, h, hex);
    }

    fclose(cat);
    *out = output;
    return 1;
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
BestMatch find_best_match_any(RGBValues color, int w, int h, const Catalog* catalog) {

    long minDiff = LONG_MAX;
    int minIndex = -1;

    for (int i = 0; i < catalog->size; i++) {
        Brick current = catalog->bricks[i];

        // must match size
        if (current.width != w || current.height != h) continue;
        long diff = color_dist2(color, current.color);
        if (diff < minDiff) {
            minDiff = diff;
            minIndex = i;
        }
    }
    BestMatch result = {minIndex, minDiff};
    return result;
}
//uses stock left to find the best match
BestMatch find_best_match_in_stock(RGBValues color, int w, int h, const Catalog* catalog) {

    long minDiff = LONG_MAX;
    int minIndex = -1;

    for (int i = 0; i < catalog->size; i++) {
        Brick current = catalog->bricks[i];

        if (current.width != w || current.height != h) continue;
        if (current.stock <= 0) continue;
        long diff = color_dist2(color, current.color);
        if (diff < minDiff) {
            minDiff = diff;
            minIndex = i;
        }
    }
    BestMatch result = {minIndex, minDiff};
    return result;
}
//uses stock left to find the best match while caring about the price
static BestMatch find_best_match_price_bias(RGBValues color, int w, int h, const Catalog* catalog, int stock_mode, int tol_pct) {
    long bestDiff = LONG_MAX;
    int bestIndex = -1;

    for (int i = 0; i < catalog->size; i++) {
        const Brick* b = &catalog->bricks[i];
        if (b->width != w || b->height != h) continue;
        if (stock_mode == STOCK_STRICT && b->stock <= 0) continue;
        long diff = color_dist2(color, b->color);
        if (diff < bestDiff) {
            bestDiff = diff;
            bestIndex = i;
        }
    }
    if (bestIndex < 0) {
        BestMatch none = {-1, LONG_MAX};
        return none;
    }

    long allowed = bestDiff + (bestDiff * tol_pct) / 100;
    long bestPrice = LONG_MAX;
    int chosen = bestIndex;
    long chosenDiff = bestDiff;

    for (int i = 0; i < catalog->size; i++) {
        const Brick* b = &catalog->bricks[i];
        if (b->width != w || b->height != h) continue;
        if (stock_mode == STOCK_STRICT && b->stock <= 0) continue;
        long diff = color_dist2(color, b->color);
        if (diff > allowed) continue;
        long price = price_to_cents(b->price);
        if (price < bestPrice || (price == bestPrice && diff < chosenDiff)) {
            bestPrice = price;
            chosen = i;
            chosenDiff = diff;
        }
    }

    BestMatch result = {chosen, chosenDiff};
    return result;
}


/* Generates a 1x1 tiling of the image, writes to file, and prints stats.
 * After some testing, it appears to be slower than the quadtree.
 * Input: Image, Catalog, and the desired output filename.
 * Output: void (writes file and prints accuracy/price/missing stock). */
int solve_1x1(const Image* image, Catalog* catalog, Solution* out, int mode_stock) {
    solution_init(out, "sol_1x1");

    for (int i = 0; i < image->height; i++) {
        for (int j = 0; j < image->width; j++) {
            RGBValues px = image->pixels[i * image->canvasDims + j];
            if (px.r < 0) continue;

            BestMatch bestBrick = (mode_stock == STOCK_STRICT)
                ? find_best_match_in_stock(px, 1, 1, catalog)
                : find_best_match_any(px, 1, 1, catalog);

            int bestId = bestBrick.index;
            if (bestId < 0) return 0;
            if (!consume_stock(catalog, bestId, mode_stock, &out->stock_breaks)) return 0;

            out->price_cents += price_to_cents(catalog->bricks[bestId].price);
            out->quality += bestBrick.diff;
            if (!solution_push(out, catalog->bricks[bestId].name, j, i, 0)) return 0;
        }
    }
    return 1;
}

/* Greedy tiling preferring 4x2/2x4 bricks, falling back to 1x1.
 * Input: Image, Catalog, output solution, and stock mode.
 * Output: 1 on success, 0 on failure. */
int solve_4x2_mix(const Image* image, Catalog* catalog, Solution* out, int mode_stock) {
    solution_init(out, "sol_4x2_mix");

    unsigned char* covered = calloc(image->canvasDims * image->canvasDims, sizeof(unsigned char));
    if (!covered) return 0;

    for (int y = 0; y < image->height; y++) {
        for (int x = 0; x < image->width; x++) {
            int idx = y * image->canvasDims + x;
            if (covered[idx]) continue;
            if (image->pixels[idx].r < 0) continue;

            int best_w = 0;
            int best_h = 0;
            int best_id = -1;
            long best_err = LONG_MAX;

            const int cand_w[2] = {4, 2};
            const int cand_h[2] = {2, 4};
            for (int c = 0; c < 2; c++) {
                int w = cand_w[c];
                int h = cand_h[c];
                if (!region_can_place(image, covered, x, y, w, h)) continue;

                RegionData rd = avg_and_var(*image, x, y, w, h);
                if (rd.count == 0) continue;

                BestMatch best = (mode_stock == STOCK_STRICT)
                    ? find_best_match_in_stock(rd.averageColor, w, h, catalog)
                    : find_best_match_any(rd.averageColor, w, h, catalog);
                if (best.index < 0) continue;

                long err = piece_error_region(image, x, y, w, h, catalog->bricks[best.index].color);
                if (err < best_err) {
                    best_err = err;
                    best_id = best.index;
                    best_w = w;
                    best_h = h;
                }
            }

            if (best_id >= 0) {
                if (!consume_stock(catalog, best_id, mode_stock, &out->stock_breaks)) {
                    free(covered);
                    return 0;
                }
                Brick* b = &catalog->bricks[best_id];
                out->price_cents += price_to_cents(b->price);
                out->quality += best_err;
                if (!solution_push(out, b->name, x, y, 0)) {
                    free(covered);
                    return 0;
                }
                mark_region(covered, image, x, y, best_w, best_h);
                continue;
            }

            BestMatch best1 = (mode_stock == STOCK_STRICT)
                ? find_best_match_in_stock(image->pixels[idx], 1, 1, catalog)
                : find_best_match_any(image->pixels[idx], 1, 1, catalog);
            if (best1.index < 0) {
                free(covered);
                return 0;
            }
            if (!consume_stock(catalog, best1.index, mode_stock, &out->stock_breaks)) {
                free(covered);
                return 0;
            }
            Brick* b1 = &catalog->bricks[best1.index];
            out->price_cents += price_to_cents(b1->price);
            out->quality += best1.diff;
            if (!solution_push(out, b1->name, x, y, 0)) {
                free(covered);
                return 0;
            }
            covered[idx] = 1;
        }
    }

    free(covered);
    return 1;
}

/* The core recursive function. Splits regions based on variance and matches bricks to leaves.
 * Input: Image, current recursion coords (x,y,w,h), threshold, file pointer, catalog.
 * Output: Returns the current Node* (links children to parents). */
Node* QUADTREE_RAW(const Image* image, int x, int y, int w, int h, int thresh, Catalog* catalog, Solution* sol, int stock_mode, int match_mode) {

    RegionData rd = avg_and_var(*image, x, y, w, h);
    int can_split = (w > 1 || h > 1);
    int should_split = do_we_split(*image, x, y, w, h, rd, thresh, catalog, stock_mode);

    if (!should_split && rd.count > 0) {
        BestMatch best;
        if (match_mode == MATCH_PRICE_BIAS) {
            best = find_best_match_price_bias(rd.averageColor, w, h, catalog, stock_mode, PRICE_QUALITY_TOL_PCT);
        } else {
            best = (stock_mode == STOCK_STRICT)
                ? find_best_match_in_stock(rd.averageColor, w, h, catalog)
                : find_best_match_any(rd.averageColor, w, h, catalog);
        }
        if (best.index < 0 && can_split) should_split = 1;
    }

    if (should_split && can_split) {
        int halfW = w / 2;
        int halfH = h / 2;

        Node* node = make_new_node(x, y, w, h, 0, rd.averageColor);
        node->child[0] = QUADTREE_RAW(image, x,       y,       halfW, halfH, thresh, catalog, sol, stock_mode, match_mode);
        node->child[1] = QUADTREE_RAW(image, x+halfW, y,       halfW, halfH, thresh, catalog, sol, stock_mode, match_mode);
        node->child[2] = QUADTREE_RAW(image, x,       y+halfH, halfW, halfH, thresh, catalog, sol, stock_mode, match_mode);
        node->child[3] = QUADTREE_RAW(image, x+halfW, y+halfH, halfW, halfH, thresh, catalog, sol, stock_mode, match_mode);
        return node;
    }

    if (rd.count > 0) {
        BestMatch best;
        if (match_mode == MATCH_PRICE_BIAS) {
            best = find_best_match_price_bias(rd.averageColor, w, h, catalog, stock_mode, PRICE_QUALITY_TOL_PCT);
        } else {
            best = (stock_mode == STOCK_STRICT)
                ? find_best_match_in_stock(rd.averageColor, w, h, catalog)
                : find_best_match_any(rd.averageColor, w, h, catalog);
        }

        if (best.index >= 0 && consume_stock(catalog, best.index, stock_mode, &sol->stock_breaks)) {
            Brick* b = &catalog->bricks[best.index];
            sol->price_cents += price_to_cents(b->price);
            sol->quality += piece_error_region(image, x, y, w, h, b->color);
            solution_push(sol, b->name, x, y, 0);
        }
    }

    return make_new_node(x, y, w, h, 1, rd.averageColor);  
}

/*A capsule function that runs the Quadtree algorithm, handles file I/O, and reports stats.
 * Input: Image, Catalog, output filename, and variance threshold.
 * Output: The root Node of the generated tree. */
Node* solve_quadtree(const Image* img, Catalog* catalog, int threshold, Solution* out, int stock_mode, int match_mode) {
    solution_init(out, "sol_quadtree");
    return QUADTREE_RAW(img, 0, 0, img->canvasDims, img->canvasDims, threshold, catalog, out, stock_mode, match_mode);
}


/////////////////////// MAIN FUNCTION //////////////////////////

int main(int argc, char *argv[]) {

    if (argc < 3) {
        fprintf(stderr, "How to use: %s <image_file> <catalog_file> [threshold_low] [threshold_high] [outdir]\n", argv[0]);
        return 1;
    }

    const char* imagePath = argv[1]; 
    const char* catalogPath = argv[2];
    int threshold_low = 500;
    int threshold_high = 2000;
    if (argc >= 4) threshold_low = atoi(argv[3]);
    if (argc >= 5) threshold_high = atoi(argv[4]);
    const char* outdir = (argc >= 6) ? argv[5] : ".";

    Catalog base;
    Image img;
    if (!load_catalog(catalogPath, &base)) return 1;
    if (!load_image(imagePath, &img)) {
        free(base.bricks);
        return 1;
    }

    Catalog cat1, cat2, cat3, cat4, cat5, cat6;
    cat1.bricks = NULL; cat1.size = 0;
    cat2.bricks = NULL; cat2.size = 0;
    cat3.bricks = NULL; cat3.size = 0;
    cat4.bricks = NULL; cat4.size = 0;
    cat5.bricks = NULL; cat5.size = 0;
    cat6.bricks = NULL; cat6.size = 0;
    if (!catalog_clone(&base, &cat1) ||
        !catalog_clone(&base, &cat2) ||
        !catalog_clone(&base, &cat3) ||
        !catalog_clone(&base, &cat4) ||
        !catalog_clone(&base, &cat5) ||
        !catalog_clone(&base, &cat6)) {
        fprintf(stderr, "Catalog clone failed\n");
        free(base.bricks);
        free(img.pixels);
        free(cat1.bricks);
        free(cat2.bricks);
        free(cat3.bricks);
        free(cat4.bricks);
        free(cat5.bricks);
        free(cat6.bricks);
        return 1;
    }

    Solution sol1, sol2, sol3, sol4, sol5, sol6;
    solution_init(&sol1, "sol_1x1_strict");
    solution_init(&sol2, "sol_1x1_relax");
    solution_init(&sol3, "sol_quadtree_strict");
    solution_init(&sol4, "sol_quadtree_relax");
    solution_init(&sol5, "sol_4x2_strict");
    solution_init(&sol6, "sol_4x2_relax");

    if (!solve_1x1(&img, &cat1, &sol1, STOCK_STRICT)) {
        fprintf(stderr, "solve_1x1 strict failed\n");
        solution_free(&sol1);
        solution_init(&sol1, "sol_1x1_strict");
    }
    if (!solve_1x1(&img, &cat2, &sol2, STOCK_RELAX)) {
        fprintf(stderr, "solve_1x1 relax failed\n");
        solution_free(&sol2);
        solution_init(&sol2, "sol_1x1_relax");
    }

    Node* root1 = solve_quadtree(&img, &cat3, threshold_low, &sol3, STOCK_STRICT, MATCH_COLOR);
    if (!root1) {
        fprintf(stderr, "solve_quadtree strict failed\n");
        solution_free(&sol3);
        solution_init(&sol3, "sol_quadtree_strict");
    }
    Node* root2 = solve_quadtree(&img, &cat4, threshold_high, &sol4, STOCK_RELAX, MATCH_PRICE_BIAS);
    if (!root2) {
        fprintf(stderr, "solve_quadtree relax failed\n");
        solution_free(&sol4);
        solution_init(&sol4, "sol_quadtree_relax");
    }

    if (!solve_4x2_mix(&img, &cat5, &sol5, STOCK_STRICT)) {
        fprintf(stderr, "solve_4x2 strict failed\n");
        solution_free(&sol5);
        solution_init(&sol5, "sol_4x2_strict");
    }
    if (!solve_4x2_mix(&img, &cat6, &sol6, STOCK_RELAX)) {
        fprintf(stderr, "solve_4x2 relax failed\n");
        solution_free(&sol6);
        solution_init(&sol6, "sol_4x2_relax");
    }

    char path1[256], path2[256], path3[256], path4[256], path5[256], path6[256];
    build_path(path1, sizeof(path1), outdir, "solution_1x1_strict.txt");
    build_path(path2, sizeof(path2), outdir, "solution_1x1_relax.txt");
    build_path(path3, sizeof(path3), outdir, "solution_quadtree_strict.txt");
    build_path(path4, sizeof(path4), outdir, "solution_quadtree_relax.txt");
    build_path(path5, sizeof(path5), outdir, "solution_4x2_strict.txt");
    build_path(path6, sizeof(path6), outdir, "solution_4x2_relax.txt");

    if (!write_solution_file(&sol1, path1)) fprintf(stderr, "Failed to write %s\n", path1);
    if (!write_solution_file(&sol2, path2)) fprintf(stderr, "Failed to write %s\n", path2);
    if (!write_solution_file(&sol3, path3)) fprintf(stderr, "Failed to write %s\n", path3);
    if (!write_solution_file(&sol4, path4)) fprintf(stderr, "Failed to write %s\n", path4);
    if (!write_solution_file(&sol5, path5)) fprintf(stderr, "Failed to write %s\n", path5);
    if (!write_solution_file(&sol6, path6)) fprintf(stderr, "Failed to write %s\n", path6);

    print_solution_summary_stdout(path1, &sol1);
    print_solution_summary_stdout(path2, &sol2);
    print_solution_summary_stdout(path3, &sol3);
    print_solution_summary_stdout(path4, &sol4);
    print_solution_summary_stdout(path5, &sol5);
    print_solution_summary_stdout(path6, &sol6);

    free_QUADTREE(root1);
    free_QUADTREE(root2);
    solution_free(&sol1);
    solution_free(&sol2);
    solution_free(&sol3);
    solution_free(&sol4);
    solution_free(&sol5);
    solution_free(&sol6);
    free(img.pixels);
    free(base.bricks);
    free(cat1.bricks);
    free(cat2.bricks);
    free(cat3.bricks);
    free(cat4.bricks);
    free(cat5.bricks);
    free(cat6.bricks);

    return 0;
}
