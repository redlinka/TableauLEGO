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
#define MAX_PIXEL_ERR 195075

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

typedef struct {
    char brick_name[32];
    int x;
    int y;
    int rot;
} Placement;

typedef struct {
    char name[64];
    Placement* placements;
    int count;
    int capacity;
    long price_cents;
    double quality;
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

static int has_piece_NH(const Catalog* catalog, int w, int h);
static int has_piece_NH_in_stock(const Catalog* catalog, int w, int h);
static BestMatch best_match_bias(RGBValues color, int w, int h, const Catalog* catalog, int stock_mode, int tol_pct);
static int region_can_place(const Image* image, const unsigned char* covered, int x, int y, int w, int h);
static void mark_region(unsigned char* covered, const Image* image, int x, int y, int w, int h);

/** a function that returns the closest power of 2 greater than or equal to n.
 * @param: n (integer to check)
 * @return: The computed power of 2. Used for padding the canvas. */
int biggest_pow_2(int n) {
    int p = 1;
    if (n <= 1) return 1;
    while (p < n){ 
        p <<= 1;
    }
    return p;
}

/** parses a hex string (e.g., "FFFFFF") into an RGB struct.
 * @param: hex string (must be 6 chars).
 * @return: RGBValues struct. Exits program on invalid format. */
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

/** parsers the holes string from the catalog into an integer array.
 * @param: string like "0123" or "-1", and the target int array.
 * @return: Modifies the 'holes' array in place. */
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

/** calculates the squared euclidean distance between two colors.
 * @param: two RGBValues to compare.
 * @return: integer difference (lower means better match). */
long color_dist2(RGBValues p1, RGBValues p2) {
    return
           (long)(p1.r - p2.r) * (p1.r - p2.r)
         + (long)(p1.g - p2.g) * (p1.g - p2.g)
         + (long)(p1.b - p2.b) * (p1.b - p2.b);
}

/** Calculates average color and variance for a specific rectangular region.
 * @param: Image, starting coords (x,y), and dimensions (w,h) of the block.
 * @return: RegionData struct containing the mean color and variance score. */
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

/** a function that decides if the current Quadtree node needs to be split further.
 * @param: dimensions, calculated RegionData, and user-defined threshold.
 * @return: 1 (true) if we split, 0 (false) if it's a leaf. */
int do_we_split(Image reg, int regX, int regY, int currentW, int currentH, RegionData regD, int thresh, const Catalog* catalog, int stock_mode) {

    if (currentW <= 1 && currentH <= 1) return 0;
    if (regD.count == 0) return 0; // fully NULL region, keep as leaf
    if (stock_mode == STOCK_STRICT) {
        if (!has_piece_NH_in_stock(catalog, currentW, currentH)) return 1;
    } else {
        if (!has_piece_NH(catalog, currentW, currentH)) return 1;
    }
    if(regD.variance >= thresh) return 1; // if the variance is above the threshold, we split
    if(currentW > MAX_REGION_SIZE || currentH > MAX_REGION_SIZE) return 1;

    /* if the current quadtree region goes beyond the original image, we split.
     *we ideally don't want this to happen because it makes the edges of the image very ugly and cost inefficient
     *to prevent that, we will recommand to the user to make their images dimensions a multiple of 16.*/
    if(regX + currentW > reg.width || regY + currentH > reg.height) return 1;
    
    return 0;
}


/** creates and initializes a new Quadtree Node.
 * @param: geometry (x,y,w,h), leaf status, and average color.
 * @return: Pointer to the new Node with children set to NULL. */
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

/** Debug function: Prints the pixel values of the entire canvas to console.
 * @param: Image struct.
 * @return: void. */
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

/** Recursively frees memory for the entire Quadtree.
 * @param: Root node of the tree.
 * @return: void. */
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

// returns if a piece of matching dimensions exits in the catalog, ignoring the holes
static int has_piece_NH(const Catalog* catalog, int w, int h) {
    for (int i = 0; i < catalog->size; i++) {
        if (catalog->bricks[i].width == w && catalog->bricks[i].height == h) return 1;
    }
    return 0;
}

// returns if a piece of matching dimensions exits in the inventory, ignoring the holes
static int has_piece_NH_in_stock(const Catalog* catalog, int w, int h) {
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

    fprintf(f, "%ld %.2f\n", sol->price_cents, sol->quality);

    for (int i = 0; i < sol->count; i++) {
        fprintf(f, "%s,%d,%d,%d\n",
            sol->placements[i].brick_name,
            sol->placements[i].rot,
            sol->placements[i].x,
            sol->placements[i].y);
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

static void solution_summary(const char* filename, const Solution* sol) {
    printf("%s %ld %.2f %d\n", filename, sol->price_cents, sol->quality, sol->stock_breaks);
}

static void holes_to_string(const int* holes, char* out, size_t out_size) {
    if (!holes || !out || out_size == 0) return;
    if (holes[0] < 0) {
        snprintf(out, out_size, "-1");
        return;
    }
    size_t pos = 0;
    for (int i = 0; i < MAX_HOLE_NUMBER; i++) {
        if (holes[i] < 0) break;
        if (pos + 2 > out_size) break;
        out[pos++] = (char)('0' + holes[i]);
    }
    out[pos] = '\0';
}

///////////////////////////////////FORMATTING FUNCTIONS//////////////////////////////////////


/** Reads the image file, creates a canvas to pad the image to the next power of 2, and fills the struct.
 * @param: file path to the image text file.
 * @return: A fully populated Image struct. Exits on file error. */
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

/** parses the catalog file and fills the Catalog struct with Brick data.
 * @param: file path to the catalog text file.
 * @return: A Catalog struct containing the array of Bricks and the total count. */
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


/** runs through the catalog to find the brick with the closest color match.
 * and the same dimensions.
 * @param: target color, target dimensions (w,h), and the catalog.
 * @return: BestMatch struct containing the index of the best brick and the diff score. */
BestMatch best_match_any(RGBValues color, int w, int h, const Catalog* catalog) {

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

BestMatch best_match_stock(RGBValues color, int w, int h, const Catalog* catalog) {

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

static BestMatch best_match_bias(RGBValues color, int w, int h, const Catalog* catalog, int stock_mode, int tol_pct) {
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


/** Generates a 1x1 tiling of the image, writes to file, and prints stats.
 * After some testing, it appears to be slower than the quadtree.
 * @param: Image, Catalog,the desired output filename, and wether or not we want to keep track of stock or not.
 * @return: void (writes file and prints accuracy/price/missing stock). */
int solve_1x1(const Image* image, Catalog* catalog, Solution* out, int mode_stock) {
    solution_init(out, "sol_1x1");

    for (int i = 0; i < image->height; i++) {
        for (int j = 0; j < image->width; j++) {
            RGBValues px = image->pixels[i * image->canvasDims + j];
            if (px.r < 0) continue;

            BestMatch bestBrick = (mode_stock == STOCK_STRICT)
                ? best_match_stock(px, 1, 1, catalog)
                : best_match_any(px, 1, 1, catalog);

            int bestId = bestBrick.index;
            if (bestId < 0) return 0;
            if (!consume_stock(catalog, bestId, mode_stock, &out->stock_breaks)) return 0;

            out->price_cents += price_to_cents(catalog->bricks[bestId].price);

            out->quality += (double)bestBrick.diff;

            if (!solution_push(out, catalog->bricks[bestId].name, j, i, 0)) return 0;
        }
    }
    long long max_err = (long long)image->width * image->height * MAX_PIXEL_ERR;
    if (max_err > 0) {
        out->quality = 100.0 * (1.0 - (out->quality / (double)max_err));
    } else {
        out->quality = 0.0;
    }

    return 1;
}

/** Tiling with selected primary dimensions, creating a basket weave pattern.
 * shrinks to fit image boundaries OR variance constraints.
 * @param: Image, Catalog, output Solution struct, stock mode, preferred width and height, variance threshold.
 * @return: 1 on success, 0 on failure.
 */
int tile_with_selected(const Image* image, Catalog* catalog, Solution* out, int mode_stock, int pref_w, int pref_h, double threshold) {
    // 1. Rename solution based on selected dims
    char solName[64];
    snprintf(solName, sizeof(solName), "sol_%dx%d_weave", pref_w, pref_h);
    solution_init(out, solName);

    unsigned char* covered = calloc(image->canvasDims * image->canvasDims, sizeof(unsigned char));
    if (!covered) return 0;

    // TWEAK 1: Use MIN for tight, 1-by-1 alternation (Herringbone style)
    int grid_size = MIN(pref_w, pref_h);
    if (grid_size < 1) grid_size = 1; // Safety

    for (int y = 0; y < image->height; y++) {
        for (int x = 0; x < image->width; x++) {
            int idx = y * image->canvasDims + x;
            if (covered[idx]) continue;
            if (image->pixels[idx].r < 0) continue;

            int best_w = 0;
            int best_h = 0;
            int best_id = -1;
            int best_rot = 0;
            long best_err = LONG_MAX;

            int attempts[2][2] = { {pref_w, pref_h}, {pref_h, pref_w} };

            // DETERMINISTIC ALTERNATION
            int gridX = x / grid_size;
            int gridY = y / grid_size;

            if ((gridX + gridY) % 2 != 0) {
                int tW = attempts[0][0]; int tH = attempts[0][1];
                attempts[0][0] = attempts[1][0]; attempts[0][1] = attempts[1][1];
                attempts[1][0] = tW;             attempts[1][1] = tH;
            }

            for (int c = 0; c < 2; c++) {
                int base_w = attempts[c][0];
                int base_h = attempts[c][1];

                int len = MAX(base_w, base_h);
                int thick = MIN(base_w, base_h);
                int is_horizontal = (base_w >= base_h);

                // Smart Shrinking Loop
                for (int l = len; l >= 2; l--) {
                    int target_w = is_horizontal ? l : thick;
                    int target_h = is_horizontal ? thick : l;

                    if (!region_can_place(image, covered, x, y, target_w, target_h)) continue;

                    RegionData rd = avg_and_var(*image, x, y, target_w, target_h);
                    if (rd.count == 0) continue;

                    // TWEAK 2: The Variance Constraint
                    // If the area is too messy for this brick size, skip it.
                    // The loop will automatically try the next smaller size (l-1).
                    if (rd.variance > threshold) continue;

                    // --- Catalog Search (Universal) ---
                    int found_id = -1;
                    int found_rot = 0;

                    BestMatch matchA = (mode_stock == STOCK_STRICT)
                        ? best_match_stock(rd.averageColor, target_w, target_h, catalog)
                        : best_match_any(rd.averageColor, target_w, target_h, catalog);

                    BestMatch matchB = (mode_stock == STOCK_STRICT)
                        ? best_match_stock(rd.averageColor, target_h, target_w, catalog)
                        : best_match_any(rd.averageColor, target_h, target_w, catalog);

                    if (matchA.index >= 0 && (matchB.index < 0 || matchA.diff <= matchB.diff)) {
                        found_id = matchA.index;
                        found_rot = 0;
                    } else if (matchB.index >= 0) {
                        found_id = matchB.index;
                        found_rot = 1;
                    }

                    if (found_id < 0) continue;

                    long err = piece_error_region(image, x, y, target_w, target_h, catalog->bricks[found_id].color);

                    if (err < best_err) {
                        best_err = err;
                        best_id = found_id;
                        best_w = target_w;
                        best_h = target_h;
                        best_rot = found_rot;
                    }

                    // Found a fit? Stop shrinking.
                    break;
                }
                if (best_id >= 0) break;
            }

            if (best_id >= 0) {
                if (!consume_stock(catalog, best_id, mode_stock, &out->stock_breaks)) {
                    free(covered); return 0;
                }
                Brick* b = &catalog->bricks[best_id];
                out->price_cents += price_to_cents(b->price);
                out->quality += (long long)best_err;

                if (!solution_push(out, b->name, x, y, best_rot)) {
                    free(covered); return 0;
                }
                mark_region(covered, image, x, y, best_w, best_h);
                continue;
            }

            // Fallback to 1x1
            BestMatch best1 = (mode_stock == STOCK_STRICT)
                ? best_match_stock(image->pixels[idx], 1, 1, catalog)
                : best_match_any(image->pixels[idx], 1, 1, catalog);

            if (best1.index < 0) { free(covered); return 0; }
            if (!consume_stock(catalog, best1.index, mode_stock, &out->stock_breaks)) {
                free(covered); return 0;
            }
            Brick* b1 = &catalog->bricks[best1.index];
            out->price_cents += price_to_cents(b1->price);

            out->quality += (long long)best1.diff;

            if (!solution_push(out, b1->name, x, y, 0)) {
                free(covered); return 0;
            }
            covered[idx] = 1;
        }
    }
    long long max_err = (long long)image->width * image->height * MAX_PIXEL_ERR;
    if (max_err > 0) {
        out->quality = 100.0 * (1.0 - (out->quality / (double)max_err));
    } else {
        out->quality = 0.0;
    }

    free(covered);
    return 1;
}

/** The core quadtree recursive function. Splits regions based on variance and matches bricks to leaves.
 * Input: Image, current recursion coords (x,y,w,h), threshold, file pointer, catalog.
 * Output: Returns the current Node* (links children to parents). */
Node* QUADTREE_RAW(const Image* image, int x, int y, int w, int h, int thresh, Catalog* catalog, Solution* sol, int stock_mode, int match_mode) {

    RegionData rd = avg_and_var(*image, x, y, w, h);
    int can_split = (w > 1 || h > 1);
    int should_split = do_we_split(*image, x, y, w, h, rd, thresh, catalog, stock_mode);

    if (!should_split && rd.count > 0) {
        BestMatch best;
        if (match_mode == MATCH_PRICE_BIAS) {
            best = best_match_bias(rd.averageColor, w, h, catalog, stock_mode, PRICE_QUALITY_TOL_PCT);
        } else {
            best = (stock_mode == STOCK_STRICT)
                ? best_match_stock(rd.averageColor, w, h, catalog)
                : best_match_any(rd.averageColor, w, h, catalog);
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
            best = best_match_bias(rd.averageColor, w, h, catalog, stock_mode, PRICE_QUALITY_TOL_PCT);
        } else {
            best = (stock_mode == STOCK_STRICT)
                ? best_match_stock(rd.averageColor, w, h, catalog)
                : best_match_any(rd.averageColor, w, h, catalog);
        }

        if (best.index >= 0 && consume_stock(catalog, best.index, stock_mode, &sol->stock_breaks)) {
            Brick* b = &catalog->bricks[best.index];
            sol->price_cents += price_to_cents(b->price);
            sol->quality += (long long)piece_error_region(image, x, y, w, h, b->color);
            solution_push(sol, b->name, x, y, 0);
        }
    }

    return make_new_node(x, y, w, h, 1, rd.averageColor);  
}

/** A capsule function that runs the Quadtree algorithm, handles file I/O, and reports stats.
 * Input: Image, Catalog, output filename, and variance threshold.
 * Output: The root Node of the generated tree. */
Node* solve_quadtree(const Image* img, Catalog* catalog, int threshold, Solution* out, int stock_mode, int match_mode) {
    solution_init(out, "sol_quadtree");

    Node* root = QUADTREE_RAW(img, 0, 0, img->canvasDims, img->canvasDims, threshold, catalog, out, stock_mode, match_mode);

    const long long max_err = (long long)img->width * img->height * MAX_PIXEL_ERR;
    if (max_err > 0) {
        out->quality = 100.0 * (1.0 - (out->quality / (double)max_err));
    } else {
        out->quality = 0.0;
    }
    return root;
}


/////////////////////// MAIN FUNCTION //////////////////////////

#ifndef UNIT_TEST
int main(int argc, char *argv[]) {

    // 1. Basic Argument Validation
    if (argc < 6) {
        fprintf(stderr, "Usage: %s <hex_image> <catalog> <output_file> <algo> <mode> [args...]\n", argv[0]);
        fprintf(stderr, "Algorithms:\n");
        fprintf(stderr, "  1x1        (No extra args)\n");
        fprintf(stderr, "  quadtree   <threshold>\n");
        fprintf(stderr, "  tile       <width> <height> <threshold>\n");
        fprintf(stderr, "Modes: strict | relax\n");
        return 1;
    }

    // 2. Parse Common Arguments
    const char* imagePath = argv[1];
    const char* catalogPath = argv[2];
    const char* outputPath = argv[3];
    const char* algo = argv[4];
    const char* modeStr = argv[5];

    // Parse Mode
    int mode = STOCK_STRICT;
    if (strcmp(modeStr, "relax") == 0) {
        mode = STOCK_RELAX;
    } else if (strcmp(modeStr, "strict") != 0) {
        fprintf(stderr, "Error: Mode must be 'strict' or 'relax'. Got '%s'.\n", modeStr);
        return 1;
    }

    // 3. Load Resources
    Catalog base;
    Image img;

    if (!load_catalog(catalogPath, &base)) {
        fprintf(stderr, "Failed to load catalog: %s\n", catalogPath);
        return 1;
    }
    if (!load_image(imagePath, &img)) {
        fprintf(stderr, "Failed to load image: %s\n", imagePath);
        free(base.bricks);
        return 1;
    }

    // Clone catalog for the simulation
    Catalog workingCat;
    if (!catalog_clone(&base, &workingCat)) {
        fprintf(stderr, "Memory error: failed to clone catalog.\n");
        free(base.bricks);
        free(img.pixels);
        return 1;
    }

    Solution sol;
    solution_init(&sol, "custom_run");
    Node* root = NULL; // For quadtree cleanup
    int success = 0;

    // 4. Algorithm Selection & Execution
    if (strcmp(algo, "1x1") == 0) {
        // No extra args needed
        success = solve_1x1(&img, &workingCat, &sol, mode);
    }
    else if (strcmp(algo, "quadtree") == 0) {
        // Expects 1 extra arg: threshold (argv[6])
        if (argc < 7) {
            fprintf(stderr, "Error: 'quadtree' requires a threshold argument.\n");
            fprintf(stderr, "Usage: ... quadtree <mode> <threshold>\n");
            goto cleanup;
        }
        int threshold = atoi(argv[6]);
        // Auto-select match mode: Strict -> Color, Relax -> Price Bias
        int matchMode = (mode == STOCK_STRICT) ? MATCH_COLOR : MATCH_PRICE_BIAS;

        root = solve_quadtree(&img, &workingCat, threshold, &sol, mode, matchMode);
        success = (root != NULL);
    }
    else if (strcmp(algo, "tile") == 0) {
        // Expects 3 extra args: w, h, threshold
        if (argc < 9) {
            fprintf(stderr, "Error: 'tile' requires dimensions and threshold.\n");
            fprintf(stderr, "Usage: ... tile <mode> <width> <height> <threshold>\n");
            goto cleanup;
        }
        int w = atoi(argv[6]);
        int h = atoi(argv[7]);
        double threshold = atof(argv[8]);

        // Check if the brick exists in either orientation (WxH or HxW)
        if (!has_piece_NH(&base, w, h) && !has_piece_NH(&base, h, w)) {
            fprintf(stderr, "Error: The catalog does not contain any brick of size %dx%d (or %dx%d).\n", w, h, h, w);
            goto cleanup;
        }
        success = tile_with_selected(&img, &workingCat, &sol, mode, w, h, threshold);
    }

    // 5. Output & Reporting
    if (success) {
        // Write the solution file
        if (!write_solution_file(&sol, outputPath)) {
            fprintf(stderr, "Failed to write solution file: %s\n", outputPath);
        }
        solution_summary(outputPath, &sol);
    } else {
        fprintf(stderr, "Algorithm failed to generate a valid solution.\n");
    }

    // 6. Cleanup
    cleanup:
    free_QUADTREE(root);
    solution_free(&sol);
    free(img.pixels);
    free(base.bricks);
    free(workingCat.bricks);

    return success ? 0 : 1;
}
#endif