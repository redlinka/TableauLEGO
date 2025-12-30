
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>
#include <limits.h>
#include <ctype.h>

/* Small helpers and limits used by the algorithms. */
#define MIN(X, Y) (((X) < (Y)) ? (X) : (Y))
#define MAX(X, Y) (((X) > (Y)) ? (X) : (Y))
#define NULL_PIXEL ((RGBValues){.r = -1, .g = -1, .b = -1})
#define MAX_REGION_SIZE 16
#define MAX_HOLE_NUMBER 4

/* RGB pixel values. */
typedef struct {
    int r;
    int g;
    int b;
} RGBValues;

/* Catalog brick description. */
typedef struct {
    char name[32];
    int width;
    int height;
    int holes[MAX_HOLE_NUMBER];
    RGBValues color;
    float price;
    int number;
} Brick;

/* Image buffer (padded to a power-of-two canvas for quadtree). */
typedef struct {
    RGBValues* pixels;
    int width;
    int height;
    int canvasDims;
} Image;

/* Brick catalog. */
typedef struct {
    Brick* bricks;
    int size;
} Catalog;

/* Best match for a size/color comparison. */
typedef struct {
    int index;
    int diff;
} BestMatch;

/* Best pick for a placement candidate. */
typedef struct {
    int index;
    int w;
    int h;
    int rot;
    double error;
} BestPiece;

/* Average color/variance of a region. */
typedef struct {
    RGBValues averageColor;
    double variance;
} RegionData;

/* Quadtree node. */
typedef struct Node {
    int x;
    int y;
    int w;
    int h;
    int is_leaf;
    RGBValues avg;
    struct Node* child[4];
} Node;

/* Aggregate stats for an algorithm run. */
typedef struct {
    double price;
    double error;
    int ruptures;
} AlgoStats;

/* Supported catalog formats. */
typedef enum {
    CAT_AUTO = 0,
    CAT_NACH,
    CAT_KADI,
    CAT_HELDER,
    CAT_MATHEO
} CatalogFormat;

/* Supported image formats. */
typedef enum {
    IMG_AUTO = 0,
    IMG_DIM,
    IMG_MATRIX
} ImageFormat;

/* Next power-of-two >= n, for quadtree canvas. */
static int biggest_pow_2(int n) {
    int p = 1;
    while (p < n) {
        p <<= 1;
    }
    return p;
}

/* Convert 6-char hex string to RGB. */
static RGBValues hex_to_RGB(const char *hex) {
    if (!hex || (int)strlen(hex) != 6) {
        perror("invalid hex format");
        exit(1);
    }
    RGBValues p;
    sscanf(hex, "%02x%02x%02x", &p.r, &p.g, &p.b);
    return p;
}

/* Parse hole encoding string into array. */
static void parse_holes(const char *str, int *holes) {
    if (!str || strlen(str) > MAX_HOLE_NUMBER) {
        perror("catalog format error");
        exit(1);
    }
    for (int i = 0; i < MAX_HOLE_NUMBER; i++) holes[i] = -1;
    if (strcmp(str, "-1") == 0) return;

    int k = 0;
    for (int i = 0; str[i]; i++) {
        if (str[i] >= '0' && str[i] <= '9') holes[k++] = str[i] - '0';
    }
}

/* Squared RGB distance. */
static int compare_colors(RGBValues p1, RGBValues p2) {
    return (p1.r - p2.r) * (p1.r - p2.r)
         + (p1.g - p2.g) * (p1.g - p2.g)
         + (p1.b - p2.b) * (p1.b - p2.b);
}

/* Sum of color errors over a block. */
static double block_error(Image img, int x, int y, int w, int h, RGBValues color) {
    double sum = 0.0;
    for (int yy = 0; yy < h; yy++) {
        for (int xx = 0; xx < w; xx++) {
            int ix = x + xx;
            int iy = y + yy;
            if (ix < 0 || iy < 0 || ix >= img.width || iy >= img.height) continue;
            RGBValues p = img.pixels[iy * img.canvasDims + ix];
            sum += (double)compare_colors(p, color);
        }
    }
    return sum;
}

/* Compute mean color and variance for a region. */
static RegionData avg_and_var(Image reg, int regX, int regY, int w, int h) {
    RegionData rd;
    double varR = 0, varG = 0, varB = 0;
    double sumR = 0, sumG = 0, sumB = 0;
    int count = 0;

    for (int y = regY; y < regY + h; y++) {
        for (int x = regX; x < regX + w; x++) {
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

    double avgR = sumR / count;
    double avgG = sumG / count;
    double avgB = sumB / count;
    rd.averageColor.r = (int)avgR;
    rd.averageColor.g = (int)avgG;
    rd.averageColor.b = (int)avgB;

    varR = (varR / count) - (avgR * avgR);
    varG = (varG / count) - (avgG * avgG);
    varB = (varB / count) - (avgB * avgB);

    rd.variance = varR + varG + varB;
    return rd;
}

/* Quadtree split decision based on variance/size/bounds. */
static int do_we_split(Image reg, int regX, int regY, int currentW, int currentH,
                       RegionData regD, int thresh) {
    if (regD.averageColor.r < 0) return 0;
    if (regD.variance >= thresh) return 1;
    if (currentW > MAX_REGION_SIZE) return 1;
    if (regX + currentW > reg.width || regY + currentH > reg.height) return 1;
    return 0;
}

/* Allocate and init a quadtree node. */
static Node* make_new_node(int x, int y, int w, int h, int is_leaf, RGBValues avg) {
    Node* n = malloc(sizeof(Node));
    if (!n) {
        perror("malloc for node failed");
        exit(1);
    }
    n->x = x;
    n->y = y;
    n->w = w;
    n->h = h;
    n->is_leaf = is_leaf;
    n->avg = avg;
    for (int i = 0; i < 4; i++) n->child[i] = NULL;
    return n;
}

/* Recursive quadtree cleanup. */
static void free_QUADTREE(Node* node) {
    if (!node) return;
    for (int i = 0; i < 4; i++) {
        free_QUADTREE(node->child[i]);
    }
    free(node);
}

/* Initialize an empty catalog. */
static void catalog_init(Catalog *c) {
    c->bricks = NULL;
    c->size = 0;
}

/* Append a brick to the catalog. */
static void catalog_add(Catalog *c, Brick b) {
    Brick *next = realloc(c->bricks, (c->size + 1) * sizeof(Brick));
    if (!next) {
        perror("catalog realloc failed");
        exit(1);
    }
    c->bricks = next;
    c->bricks[c->size++] = b;
}

/* Trim leading/trailing whitespace. */
static char *trim(char *s) {
    while (isspace((unsigned char)*s)) s++;
    if (*s == 0) return s;
    char *end = s + strlen(s) - 1;
    while (end > s && isspace((unsigned char)*end)) end--;
    end[1] = '\0';
    return s;
}

/* Load image with "w h" + flat hex pixel list format. */
static Image load_image_dim(const char* path) {
    char hex[7];
    Image output;

    FILE* image = fopen(path, "r");
    if (!image) {
        perror("Error opening file");
        exit(1);
    }

    int w, h;
    if (fscanf(image, "%d %d\n", &w, &h) != 2) {
        fprintf(stderr, "Invalid image file: missing dimensions\n");
        exit(1);
    }

    output.width = w;
    output.height = h;
    output.canvasDims = biggest_pow_2(MAX(w, h));

    RGBValues* temp = malloc(output.width * output.height * sizeof(RGBValues));
    RGBValues* pixels = calloc((output.canvasDims * output.canvasDims), sizeof(RGBValues));
    if (!temp || !pixels) {
        perror("Memory allocation failed");
        fclose(image);
        exit(1);
    }

    int i = 0;
    while (fscanf(image, "%6s", hex) == 1 && i < output.width * output.height) {
        temp[i++] = hex_to_RGB(hex);
    }

    for (int j = 0; j < output.canvasDims * output.canvasDims; j++) {
        pixels[j] = NULL_PIXEL;
    }

    for (int y = 0; y < output.height; y++) {
        for (int x = 0; x < output.width; x++) {
            pixels[y * output.canvasDims + x] = temp[y * output.width + x];
        }
    }

    free(temp);
    fclose(image);
    output.pixels = pixels;
    return output;
}

/* Load image with matrix-of-hex tokens format. */
static Image load_image_matrix(const char* path) {
    FILE* image = fopen(path, "r");
    if (!image) {
        perror("Error opening file");
        exit(1);
    }

    RGBValues *data = NULL;
    size_t cap = 0;
    size_t len = 0;
    int width = -1;
    int height = 0;

    char line[4096];
    while (fgets(line, sizeof(line), image)) {
        char *t = trim(line);
        if (*t == '\0') continue;

        int count = 0;
        char *tok = strtok(t, " \t\r\n");
        while (tok) {
            if (strlen(tok) != 6) {
                fprintf(stderr, "Invalid pixel token: %s\n", tok);
                exit(1);
            }
            if (len == cap) {
                cap = (cap == 0) ? 256 : cap * 2;
                RGBValues *next = realloc(data, cap * sizeof(RGBValues));
                if (!next) {
                    perror("image realloc failed");
                    exit(1);
                }
                data = next;
            }
            data[len++] = hex_to_RGB(tok);
            count++;
            tok = strtok(NULL, " \t\r\n");
        }

        if (count == 0) continue;
        if (width < 0) width = count;
        if (count != width) {
            fprintf(stderr, "Inconsistent row width in matrix image\n");
            exit(1);
        }
        height++;
    }
    fclose(image);

    if (width <= 0 || height <= 0) {
        fprintf(stderr, "Empty matrix image\n");
        exit(1);
    }

    Image output;
    output.width = width;
    output.height = height;
    output.canvasDims = biggest_pow_2(MAX(width, height));
    output.pixels = calloc(output.canvasDims * output.canvasDims, sizeof(RGBValues));
    if (!output.pixels) {
        perror("Memory allocation failed");
        exit(1);
    }
    for (int i = 0; i < output.canvasDims * output.canvasDims; i++) {
        output.pixels[i] = NULL_PIXEL;
    }
    for (int y = 0; y < height; y++) {
        for (int x = 0; x < width; x++) {
            output.pixels[y * output.canvasDims + x] = data[y * width + x];
        }
    }
    free(data);
    return output;
}

/* Auto-detect image format and load. */
static Image load_image_auto(const char* path, ImageFormat fmt) {
    if (fmt == IMG_DIM) return load_image_dim(path);
    if (fmt == IMG_MATRIX) return load_image_matrix(path);

    FILE* f = fopen(path, "r");
    if (!f) {
        perror("Error opening file");
        exit(1);
    }
    char line[256];
    if (!fgets(line, sizeof(line), f)) {
        fprintf(stderr, "Empty image file\n");
        exit(1);
    }
    fclose(f);

    int w, h;
    if (sscanf(line, "%d %d", &w, &h) == 2) {
        return load_image_dim(path);
    }
    return load_image_matrix(path);
}

/* Catalog loader: Nachnouchi CSV-like format. */
static Catalog load_catalog_nach(const char *path) {
    Catalog output;
    catalog_init(&output);

    FILE* cat = fopen(path, "r");
    if (!cat) {
        perror("Error opening catalog file");
        exit(1);
    }

    int n;
    if (fscanf(cat, "%d\n", &n) != 1) {
        fprintf(stderr, "Invalid catalog file: missing line count\n");
        exit(1);
    }

    for (int i = 0; i < n; i++) {
        Brick b = {0};
        int w, h, stock;
        char holesStr[16];
        char hex[16];
        float price;

        if (fscanf(cat, "%d,%d,%15[^,],%15[^,],%f,%d",
                   &w, &h, holesStr, hex, &price, &stock) != 6) {
            fprintf(stderr, "Invalid line %d in catalog\n", i + 1);
            exit(1);
        }

        b.width = w;
        b.height = h;
        b.price = price;
        b.number = stock;
        b.color = hex_to_RGB(hex);
        parse_holes(holesStr, b.holes);
        snprintf(b.name, sizeof(b.name), "%d-%d/%s", w, h, hex);
        catalog_add(&output, b);
    }

    fclose(cat);
    return output;
}

/* Catalog loader: Kadi whitespace-separated format. */
static Catalog load_catalog_kadi(const char *path) {
    Catalog output;
    catalog_init(&output);

    FILE* cat = fopen(path, "r");
    if (!cat) {
        perror("Error opening catalog file");
        exit(1);
    }

    while (1) {
        Brick b = {0};
        int r, g, bb, stock;
        double price;
        int w, h;
        int rd = fscanf(cat, "%31s %d %d %d %d %d %lf %d",
                        b.name, &w, &h, &r, &g, &bb, &price, &stock);
        if (rd == EOF) break;
        if (rd != 8) {
            fprintf(stderr, "Invalid KADI catalog format\n");
            exit(1);
        }
        b.width = w;
        b.height = h;
        b.color.r = r;
        b.color.g = g;
        b.color.b = bb;
        b.price = (float)price;
        b.number = stock;
        for (int i = 0; i < MAX_HOLE_NUMBER; i++) b.holes[i] = -1;
        catalog_add(&output, b);
    }
    fclose(cat);
    return output;
}

/* Catalog loader: Helder multi-section format. */
static Catalog load_catalog_helder(const char *path) {
    Catalog output;
    catalog_init(&output);

    FILE* cat = fopen(path, "r");
    if (!cat) {
        perror("Error opening catalog file");
        exit(1);
    }

    int nShape, nCol, nBrique;
    if (fscanf(cat, "%d %d %d", &nShape, &nCol, &nBrique) != 3) {
        fprintf(stderr, "Invalid Helder catalog header\n");
        exit(1);
    }

    int *W = malloc(nShape * sizeof(int));
    int *H = malloc(nShape * sizeof(int));
    char buffer[80];
    for (int i = 0; i < nShape; i++) {
        int count = fscanf(cat, "%d-%d-%s", &W[i], &H[i], buffer);
        if (count < 2 || count > 3) {
            fprintf(stderr, "Invalid shape line\n");
            exit(1);
        }
        if (count == 2) {
            int c = fgetc(cat);
            if (c == '\n' || c == ' ') {
            } else {
                ungetc(c, cat);
            }
        }
    }

    RGBValues *colors = malloc(nCol * sizeof(RGBValues));
    for (int i = 0; i < nCol; i++) {
        char hex[8] = {0};
        if (fscanf(cat, "%7s", hex) != 1) {
            fprintf(stderr, "Invalid color line\n");
            exit(1);
        }
        colors[i] = hex_to_RGB(hex);
    }

    for (int i = 0; i < nBrique; i++) {
        int iShape, iColor, price, stock;
        int rd = fscanf(cat, "%d/%d %d %d", &iShape, &iColor, &price, &stock);
        if (rd != 4) {
            fprintf(stderr, "Invalid brique line\n");
            exit(1);
        }
        Brick b = {0};
        b.width = W[iShape];
        b.height = H[iShape];
        b.color = colors[iColor];
        b.price = (float)price;
        b.number = stock;
        for (int k = 0; k < MAX_HOLE_NUMBER; k++) b.holes[k] = -1;
        snprintf(b.name, sizeof(b.name), "%d-%d/%02X%02X%02X",
                 b.width, b.height, b.color.r, b.color.g, b.color.b);
        catalog_add(&output, b);
    }

    free(W);
    free(H);
    free(colors);
    fclose(cat);
    return output;
}

/* Catalog loader: Matheo colors + pieces format. */
static Catalog load_catalog_matheo(const char *path) {
    Catalog output;
    catalog_init(&output);

    FILE* cat = fopen(path, "r");
    if (!cat) {
        perror("Error opening catalog file");
        exit(1);
    }

    RGBValues *colors = NULL;
    int nColors = 0;
    int capColors = 0;
    char line[256];
    int reading_colors = 1;

    typedef struct {
        int w;
        int h;
        int price;
        int stock;
        char holes[64];
    } PieceSpec;

    PieceSpec *pieces = NULL;
    int nPieces = 0;
    int capPieces = 0;

    while (fgets(line, sizeof(line), cat)) {
        char *t = trim(line);
        if (*t == '\0') continue;
        if (strcmp(t, "/") == 0) {
            reading_colors = 0;
            continue;
        }
        if (reading_colors) {
            if (strlen(t) != 6) {
                fprintf(stderr, "Invalid color in matheo file: %s\n", t);
                exit(1);
            }
            if (nColors == capColors) {
                capColors = (capColors == 0) ? 16 : capColors * 2;
                RGBValues *next = realloc(colors, capColors * sizeof(RGBValues));
                if (!next) {
                    perror("colors realloc failed");
                    exit(1);
                }
                colors = next;
            }
            colors[nColors++] = hex_to_RGB(t);
        } else {
            PieceSpec p = {0};
            char format[16];
            if (sscanf(t, "%15s %63s %d %d", format, p.holes, &p.price, &p.stock) != 4) {
                fprintf(stderr, "Invalid piece line: %s\n", t);
                exit(1);
            }
            if (sscanf(format, "%dx%d", &p.w, &p.h) != 2) {
                fprintf(stderr, "Invalid piece format: %s\n", format);
                exit(1);
            }
            if (nPieces == capPieces) {
                capPieces = (capPieces == 0) ? 8 : capPieces * 2;
                PieceSpec *next = realloc(pieces, capPieces * sizeof(PieceSpec));
                if (!next) {
                    perror("pieces realloc failed");
                    exit(1);
                }
                pieces = next;
            }
            pieces[nPieces++] = p;
        }
    }
    fclose(cat);

    for (int i = 0; i < nPieces; i++) {
        for (int c = 0; c < nColors; c++) {
            Brick b = {0};
            b.width = pieces[i].w;
            b.height = pieces[i].h;
            b.color = colors[c];
            b.price = (float)pieces[i].price;
            b.number = pieces[i].stock;
            for (int k = 0; k < MAX_HOLE_NUMBER; k++) b.holes[k] = -1;
            snprintf(b.name, sizeof(b.name), "%d-%d/%02X%02X%02X",
                     b.width, b.height, b.color.r, b.color.g, b.color.b);
            catalog_add(&output, b);
        }
    }

    free(colors);
    free(pieces);
    return output;
}

/* Guess catalog format based on header/markers. */
static CatalogFormat detect_catalog_format(const char *path) {
    FILE *f = fopen(path, "r");
    if (!f) {
        perror("Error opening catalog file");
        exit(1);
    }

    char line[256] = {0};
    char first[256] = {0};
    int has_slash_line = 0;
    while (fgets(line, sizeof(line), f)) {
        char *t = trim(line);
        if (*t == '\0') continue;
        if (first[0] == '\0') {
            strncpy(first, t, sizeof(first) - 1);
        }
        if (strcmp(t, "/") == 0) {
            has_slash_line = 1;
            break;
        }
    }
    fclose(f);

    if (has_slash_line) return CAT_MATHEO;
    if (strchr(first, ',')) return CAT_NACH;

    int a, b, c;
    if (sscanf(first, "%d %d %d", &a, &b, &c) == 3) return CAT_HELDER;

    char id[32];
    int w, h, r, g, bb, stock;
    double price;
    if (sscanf(first, "%31s %d %d %d %d %d %lf %d",
               id, &w, &h, &r, &g, &bb, &price, &stock) == 8) {
        return CAT_KADI;
    }

    return CAT_NACH;
}

/* Auto-detect or force a catalog format. */
static Catalog load_catalog_auto(const char *path, CatalogFormat fmt) {
    if (fmt == CAT_AUTO) fmt = detect_catalog_format(path);
    switch (fmt) {
        case CAT_NACH: return load_catalog_nach(path);
        case CAT_KADI: return load_catalog_kadi(path);
        case CAT_HELDER: return load_catalog_helder(path);
        case CAT_MATHEO: return load_catalog_matheo(path);
        default: return load_catalog_nach(path);
    }
}

/* Best in-stock brick for a given size/color. */
static BestMatch find_best_match(RGBValues color, int w, int h, Catalog catalog) {
    int minDiff = -1;
    int minIndex = -1;

    for (int i = 0; i < catalog.size; i++) {
        Brick current = catalog.bricks[i];
        if (current.number <= 0) continue;
        if (current.width != w || current.height != h) continue;
        int diff = compare_colors(color, current.color);
        if (minDiff == -1 || diff < minDiff) {
            minDiff = diff;
            minIndex = i;
        }
    }
    BestMatch result = {minIndex, minDiff};
    return result;
}

/* Best match with optional rotation (swap w/h). */
static BestMatch find_best_match_rot(RGBValues color, int w, int h, Catalog catalog, int *rot_out) {
    BestMatch exact = find_best_match(color, w, h, catalog);
    if (exact.index >= 0) {
        if (rot_out) *rot_out = 0;
        return exact;
    }
    BestMatch swapped = find_best_match(color, h, w, catalog);
    if (rot_out) *rot_out = (swapped.index >= 0) ? 90 : 0;
    return swapped;
}

/* Best match that favors lower price with limited error slack. */
static BestMatch find_best_match_price_bias(RGBValues color, int w, int h,
                                            Catalog catalog, int max_extra_error) {
    int bestErr = INT_MAX;
    for (int i = 0; i < catalog.size; i++) {
        Brick current = catalog.bricks[i];
        if (current.number <= 0) continue;
        if (current.width != w || current.height != h) continue;
        int diff = compare_colors(color, current.color);
        if (diff < bestErr) bestErr = diff;
    }

    if (bestErr == INT_MAX) {
        BestMatch none = { -1, INT_MAX };
        return none;
    }

    if (max_extra_error < 0) max_extra_error = 0;
    int limit = bestErr + max_extra_error;

    int bestIndex = -1;
    int bestDiff = INT_MAX;
    double bestPrice = 1e300;
    for (int i = 0; i < catalog.size; i++) {
        Brick current = catalog.bricks[i];
        if (current.number <= 0) continue;
        if (current.width != w || current.height != h) continue;
        int diff = compare_colors(color, current.color);
        if (diff > limit) continue;
        if (current.price < bestPrice ||
            (current.price == bestPrice && diff < bestDiff)) {
            bestPrice = current.price;
            bestDiff = diff;
            bestIndex = i;
        }
    }

    BestMatch result = { bestIndex, bestDiff };
    return result;
}

/* Write a placed piece line. */
static void emit_piece(FILE *out, const Brick *b, int x, int y, int rot) {
    fprintf(out, "%s %d %d %d\n", b->name, x, y, rot);
}

/* Decrement stock and track ruptures. */
static void update_stock(Catalog *cat, int idx, AlgoStats *stats) {
    cat->bricks[idx].number--;
    if (cat->bricks[idx].number < 0) stats->ruptures++;
}

/* Emit a "missing pieces" order file. */
static void make_order_file(Catalog catalog, const char* name) {
    FILE* f = fopen(name, "w");
    if (!f) {
        perror("Unable to create the invoice");
        exit(1);
    }

    for (int i = 0; i < catalog.size; i++) {
        Brick b = catalog.bricks[i];
        int missing = 0;
        if (b.number < 0) missing = -b.number;
        if (missing > 0) {
            fprintf(f, "%s,%d\n", b.name, missing);
        }
    }
    fclose(f);
}

/* Basic 1x1 tiling. */
static void algo_1x1(Image image, Catalog catalog, const char* out_name, AlgoStats *stats) {
    FILE* tiled = fopen(out_name, "w");
    if (!tiled) {
        perror("Error opening output");
        exit(1);
    }

    for (int i = 0; i < image.height; i++) {
        for (int j = 0; j < image.width; j++) {
            RGBValues p = image.pixels[i * image.canvasDims + j];
            BestMatch bestBrick = find_best_match(p, 1, 1, catalog);
            int bestId = bestBrick.index;
            if (bestId < 0) continue;
            stats->price += catalog.bricks[bestId].price;
            stats->error += (double)compare_colors(p, catalog.bricks[bestId].color);
            update_stock(&catalog, bestId, stats);
            emit_piece(tiled, &catalog.bricks[bestId], j, i, 0);
        }
    }

    fclose(tiled);
    make_order_file(catalog, "order_1x1.txt");
}

/* 1x1 tiling with price bias and limited color slack. */
static void algo_1x1_price_bias(Image image, Catalog catalog, const char* out_name,
                                int max_extra_error, AlgoStats *stats) {
    FILE* tiled = fopen(out_name, "w");
    if (!tiled) {
        perror("Error opening output");
        exit(1);
    }

    for (int i = 0; i < image.height; i++) {
        for (int j = 0; j < image.width; j++) {
            RGBValues p = image.pixels[i * image.canvasDims + j];
            BestMatch bestBrick = find_best_match_price_bias(p, 1, 1, catalog, max_extra_error);
            int bestId = bestBrick.index;
            if (bestId < 0) continue;
            stats->price += catalog.bricks[bestId].price;
            stats->error += (double)compare_colors(p, catalog.bricks[bestId].color);
            update_stock(&catalog, bestId, stats);
            emit_piece(tiled, &catalog.bricks[bestId], j, i, 0);
        }
    }

    fclose(tiled);
    make_order_file(catalog, "order_cheap.txt");
}

/* Quadtree recursive build with placement. */
static Node* QUADTREE_RAW(Image image, int x, int y, int w, int h, int thresh,
                          FILE* outFile, Catalog *catalog, AlgoStats *stats) {
    RegionData rd = avg_and_var(image, x, y, w, h);

    BestMatch bm = find_best_match(rd.averageColor, w, h, *catalog);
    if (bm.index < 0) {
        if (w > 1 || h > 1) {
            int halfW = w / 2;
            int halfH = h / 2;
            Node* node = make_new_node(x, y, w, h, 0, rd.averageColor);
            node->child[0] = QUADTREE_RAW(image, x, y, halfW, halfH, thresh,
                                          outFile, catalog, stats);
            node->child[1] = QUADTREE_RAW(image, x + halfW, y, halfW, halfH, thresh,
                                          outFile, catalog, stats);
            node->child[2] = QUADTREE_RAW(image, x, y + halfH, halfW, halfH, thresh,
                                          outFile, catalog, stats);
            node->child[3] = QUADTREE_RAW(image, x + halfW, y + halfH, halfW, halfH, thresh,
                                          outFile, catalog, stats);
            return node;
        }
    }

    if (do_we_split(image, x, y, w, h, rd, thresh)) {
        int halfW = w / 2;
        int halfH = h / 2;

        Node* node = make_new_node(x, y, w, h, 0, rd.averageColor);
        node->child[0] = QUADTREE_RAW(image, x, y, halfW, halfH, thresh,
                                      outFile, catalog, stats);
        node->child[1] = QUADTREE_RAW(image, x + halfW, y, halfW, halfH, thresh,
                                      outFile, catalog, stats);
        node->child[2] = QUADTREE_RAW(image, x, y + halfH, halfW, halfH, thresh,
                                      outFile, catalog, stats);
        node->child[3] = QUADTREE_RAW(image, x + halfW, y + halfH, halfW, halfH, thresh,
                                      outFile, catalog, stats);
        return node;
    }

    if (!(rd.averageColor.r < 0) && bm.index >= 0) {
        update_stock(catalog, bm.index, stats);
        emit_piece(outFile, &catalog->bricks[bm.index], x, y, 0);
        stats->price += catalog->bricks[bm.index].price;
        stats->error += block_error(image, x, y, w, h, catalog->bricks[bm.index].color);
    }

    return make_new_node(x, y, w, h, 1, rd.averageColor);
}

/* Quadtree-based tiling. */
static void algo_quadtree(Image img, Catalog catalog, const char* out_name,
                          int threshold, AlgoStats *stats) {
    FILE* outFile = fopen(out_name, "w");
    if (!outFile) {
        perror("Failed to open output file");
        exit(1);
    }

    Node* root = QUADTREE_RAW(img, 0, 0, img.canvasDims, img.canvasDims,
                              threshold, outFile, &catalog, stats);
    fclose(outFile);
    make_order_file(catalog, "order_quadtree.txt");
    free_QUADTREE(root);
}

/* 2x1/1x2 matching, then fill with 1x1. */
static void algo_match2x1(Image img, Catalog catalog, const char* out_name, AlgoStats *stats) {
    int W = img.width;
    int H = img.height;
    int *placed = calloc(W * H, sizeof(int));
    if (!placed) {
        perror("Memory allocation failed");
        exit(1);
    }

    FILE* out = fopen(out_name, "w");
    if (!out) {
        perror("Failed to open output file");
        exit(1);
    }

    for (int y = 0; y < H; y++) {
        for (int x = 0; x < W; x++) {
            int idx = y * W + x;
            if (placed[idx]) continue;

            int bestNeighbor = -1;
            int bestRot = 0;
            int bestDiff = INT_MAX;
            BestMatch bestBrick = { -1, INT_MAX };

            if (x + 1 < W && !placed[idx + 1]) {
                RGBValues c1 = img.pixels[y * img.canvasDims + x];
                RGBValues c2 = img.pixels[y * img.canvasDims + (x + 1)];
                RGBValues avg = {
                    .r = (c1.r + c2.r) / 2,
                    .g = (c1.g + c2.g) / 2,
                    .b = (c1.b + c2.b) / 2
                };
                int rot = 0;
                BestMatch bm = find_best_match_rot(avg, 2, 1, catalog, &rot);
                if (bm.index >= 0 && bm.diff < bestDiff) {
                    bestNeighbor = idx + 1;
                    bestRot = rot;
                    bestDiff = bm.diff;
                    bestBrick = bm;
                }
            }

            if (y + 1 < H && !placed[idx + W]) {
                RGBValues c1 = img.pixels[y * img.canvasDims + x];
                RGBValues c2 = img.pixels[(y + 1) * img.canvasDims + x];
                RGBValues avg = {
                    .r = (c1.r + c2.r) / 2,
                    .g = (c1.g + c2.g) / 2,
                    .b = (c1.b + c2.b) / 2
                };
                int rot = 0;
                BestMatch bm = find_best_match_rot(avg, 1, 2, catalog, &rot);
                if (bm.index >= 0 && bm.diff < bestDiff) {
                    bestNeighbor = idx + W;
                    bestRot = rot;
                    bestDiff = bm.diff;
                    bestBrick = bm;
                }
            }

            if (bestNeighbor >= 0 && bestBrick.index >= 0) {
                update_stock(&catalog, bestBrick.index, stats);
                emit_piece(out, &catalog.bricks[bestBrick.index], x, y, bestRot);
                stats->price += catalog.bricks[bestBrick.index].price;
                stats->error += block_error(img, x, y,
                                            (bestNeighbor == idx + 1) ? 2 : 1,
                                            (bestNeighbor == idx + 1) ? 1 : 2,
                                            catalog.bricks[bestBrick.index].color);
                placed[idx] = 1;
                placed[bestNeighbor] = 1;
                continue;
            }

            RGBValues p = img.pixels[y * img.canvasDims + x];
            BestMatch bm1 = find_best_match(p, 1, 1, catalog);
            if (bm1.index >= 0) {
                update_stock(&catalog, bm1.index, stats);
                emit_piece(out, &catalog.bricks[bm1.index], x, y, 0);
                stats->price += catalog.bricks[bm1.index].price;
                stats->error += (double)compare_colors(p, catalog.bricks[bm1.index].color);
                placed[idx] = 1;
            }
        }
    }

    fclose(out);
    make_order_file(catalog, "order_match2x1.txt");
    free(placed);
}

/* Temporary block info for 2x2 grouping. */
typedef struct {
    int x;
    int y;
    RGBValues avg;
    int used;
} Block2x2;

/* 2x2 detection with optional 4x2/2x4 merges, then fill. */
static void algo_blocks2x2(Image img, Catalog catalog, const char* out_name,
                           int threshold, AlgoStats *stats) {
    int W = img.width;
    int H = img.height;
    int maxBlocks = (W / 2) * (H / 2);
    Block2x2 *blocks = malloc(sizeof(Block2x2) * maxBlocks);
    int blockCount = 0;

    int *placed = calloc(W * H, sizeof(int));
    if (!blocks || !placed) {
        perror("Memory allocation failed");
        exit(1);
    }

    for (int y = 0; y + 1 < H; y += 2) {
        for (int x = 0; x + 1 < W; x += 2) {
            RegionData rd = avg_and_var(img, x, y, 2, 2);
            int maxd = (int)block_error(img, x, y, 2, 2, rd.averageColor);
            if (maxd <= threshold) {
                blocks[blockCount].x = x;
                blocks[blockCount].y = y;
                blocks[blockCount].avg = rd.averageColor;
                blocks[blockCount].used = 0;
                blockCount++;
            }
        }
    }

    FILE* out = fopen(out_name, "w");
    if (!out) {
        perror("Failed to open output file");
        exit(1);
    }

    for (int i = 0; i < blockCount; i++) {
        if (blocks[i].used) continue;
        int x = blocks[i].x;
        int y = blocks[i].y;

        int paired = 0;
        for (int j = i + 1; j < blockCount; j++) {
            if (blocks[j].used) continue;
            if (blocks[j].y == y && blocks[j].x == x + 2) {
                int diff = compare_colors(blocks[i].avg, blocks[j].avg);
                if (diff <= threshold) {
                    int rot = 0;
                    BestMatch bm = find_best_match_rot(blocks[i].avg, 4, 2, catalog, &rot);
                    if (bm.index >= 0) {
                        update_stock(&catalog, bm.index, stats);
                        emit_piece(out, &catalog.bricks[bm.index], x, y, rot);
                        stats->price += catalog.bricks[bm.index].price;
                        stats->error += block_error(img, x, y, 4, 2, catalog.bricks[bm.index].color);
                        blocks[i].used = blocks[j].used = 1;
                        paired = 1;
                        for (int dy = 0; dy < 2; dy++) {
                            for (int dx = 0; dx < 4; dx++) {
                                placed[(y + dy) * W + (x + dx)] = 1;
                            }
                        }
                        break;
                    }
                }
            }
            if (blocks[j].x == x && blocks[j].y == y + 2) {
                int diff = compare_colors(blocks[i].avg, blocks[j].avg);
                if (diff <= threshold) {
                    int rot = 0;
                    BestMatch bm = find_best_match_rot(blocks[i].avg, 2, 4, catalog, &rot);
                    if (bm.index >= 0) {
                        update_stock(&catalog, bm.index, stats);
                        emit_piece(out, &catalog.bricks[bm.index], x, y, rot);
                        stats->price += catalog.bricks[bm.index].price;
                        stats->error += block_error(img, x, y, 2, 4, catalog.bricks[bm.index].color);
                        blocks[i].used = blocks[j].used = 1;
                        paired = 1;
                        for (int dy = 0; dy < 4; dy++) {
                            for (int dx = 0; dx < 2; dx++) {
                                placed[(y + dy) * W + (x + dx)] = 1;
                            }
                        }
                        break;
                    }
                }
            }
        }
        if (paired) continue;
    }

    for (int i = 0; i < blockCount; i++) {
        if (blocks[i].used) continue;
        int x = blocks[i].x;
        int y = blocks[i].y;
        BestMatch bm = find_best_match(blocks[i].avg, 2, 2, catalog);
        if (bm.index >= 0) {
            update_stock(&catalog, bm.index, stats);
            emit_piece(out, &catalog.bricks[bm.index], x, y, 0);
            stats->price += catalog.bricks[bm.index].price;
            stats->error += block_error(img, x, y, 2, 2, catalog.bricks[bm.index].color);
            blocks[i].used = 1;
            for (int dy = 0; dy < 2; dy++) {
                for (int dx = 0; dx < 2; dx++) {
                    placed[(y + dy) * W + (x + dx)] = 1;
                }
            }
        }
    }

    for (int y = 0; y < H; y++) {
        for (int x = 0; x < W; x++) {
            int idx = y * W + x;
            if (placed[idx]) continue;
            RGBValues p = img.pixels[y * img.canvasDims + x];
            BestMatch bm = find_best_match(p, 1, 1, catalog);
            if (bm.index >= 0) {
                update_stock(&catalog, bm.index, stats);
                emit_piece(out, &catalog.bricks[bm.index], x, y, 0);
                stats->price += catalog.bricks[bm.index].price;
                stats->error += (double)compare_colors(p, catalog.bricks[bm.index].color);
            }
        }
    }

    fclose(out);
    make_order_file(catalog, "order_blocks2x2.txt");
    free(blocks);
    free(placed);
}

/* Check if a region is unoccupied. */
static int region_is_free(const int *placed, int W, int H, int x, int y, int w, int h) {
    if (x < 0 || y < 0 || x + w > W || y + h > H) return 0;
    for (int dy = 0; dy < h; dy++) {
        for (int dx = 0; dx < w; dx++) {
            if (placed[(y + dy) * W + (x + dx)]) return 0;
        }
    }
    return 1;
}

/* Mark a region as occupied. */
static void mark_region(int *placed, int W, int x, int y, int w, int h) {
    for (int dy = 0; dy < h; dy++) {
        for (int dx = 0; dx < w; dx++) {
            placed[(y + dy) * W + (x + dx)] = 1;
        }
    }
}

/* Tie-breaker: prefer larger area when errors match. */
static int should_replace_best(double err, int area, BestPiece best) {
    if (best.index < 0) return 1;
    if (err < best.error) return 1;
    if (err == best.error && area > best.w * best.h) return 1;
    return 0;
}

/* Best in-stock brick for any size at a position. */
static BestPiece find_best_piece_any(Image img, int x, int y, int *placed,
                                     Catalog *catalog) {
    BestPiece best = { .index = -1, .w = 0, .h = 0, .rot = 0, .error = 0.0 };
    int W = img.width;
    int H = img.height;

    for (int i = 0; i < catalog->size; i++) {
        Brick *b = &catalog->bricks[i];
        if (b->number <= 0) continue;

        if (region_is_free(placed, W, H, x, y, b->width, b->height)) {
            double err = block_error(img, x, y, b->width, b->height, b->color);
            if (should_replace_best(err, b->width * b->height, best)) {
                best.index = i;
                best.w = b->width;
                best.h = b->height;
                best.rot = 0;
                best.error = err;
            }
        }

        if (b->width != b->height &&
            region_is_free(placed, W, H, x, y, b->height, b->width)) {
            double err = block_error(img, x, y, b->height, b->width, b->color);
            if (should_replace_best(err, b->height * b->width, best)) {
                best.index = i;
                best.w = b->height;
                best.h = b->width;
                best.rot = 90;
                best.error = err;
            }
        }
    }

    return best;
}

/* Best in-stock brick for any size at a position, ignoring stock. */
static BestPiece find_best_piece_any_nostock(Image img, int x, int y, int *placed,
                                             Catalog *catalog) {
    BestPiece best = { .index = -1, .w = 0, .h = 0, .rot = 0, .error = 0.0 };
    int W = img.width;
    int H = img.height;

    for (int i = 0; i < catalog->size; i++) {
        Brick *b = &catalog->bricks[i];

        if (region_is_free(placed, W, H, x, y, b->width, b->height)) {
            double err = block_error(img, x, y, b->width, b->height, b->color);
            if (should_replace_best(err, b->width * b->height, best)) {
                best.index = i;
                best.w = b->width;
                best.h = b->height;
                best.rot = 0;
                best.error = err;
            }
        }

        if (b->width != b->height &&
            region_is_free(placed, W, H, x, y, b->height, b->width)) {
            double err = block_error(img, x, y, b->height, b->width, b->color);
            if (should_replace_best(err, b->height * b->width, best)) {
                best.index = i;
                best.w = b->height;
                best.h = b->width;
                best.rot = 90;
                best.error = err;
            }
        }
    }

    return best;
}

/* Sizes allowed in the combo preference pass. */
static int is_combo_size(int w, int h) {
    return (w == 2 && h == 2) || (w == 2 && h == 1) || (w == 1 && h == 2);
}

/* Best in-stock 2x2/2x1/1x2 candidate for combo pass. */
static BestPiece find_best_piece_combo(Image img, int x, int y, int *placed,
                                       Catalog *catalog) {
    BestPiece best = { .index = -1, .w = 0, .h = 0, .rot = 0, .error = 0.0 };
    int W = img.width;
    int H = img.height;

    for (int i = 0; i < catalog->size; i++) {
        Brick *b = &catalog->bricks[i];
        if (b->number <= 0) continue;

        if (is_combo_size(b->width, b->height) &&
            region_is_free(placed, W, H, x, y, b->width, b->height)) {
            double err = block_error(img, x, y, b->width, b->height, b->color);
            if (should_replace_best(err, b->width * b->height, best)) {
                best.index = i;
                best.w = b->width;
                best.h = b->height;
                best.rot = 0;
                best.error = err;
            }
        }

        if (b->width != b->height &&
            is_combo_size(b->height, b->width) &&
            region_is_free(placed, W, H, x, y, b->height, b->width)) {
            double err = block_error(img, x, y, b->height, b->width, b->color);
            if (should_replace_best(err, b->height * b->width, best)) {
                best.index = i;
                best.w = b->height;
                best.h = b->width;
                best.rot = 90;
                best.error = err;
            }
        }
    }

    return best;
}

/* Greedy pass to use larger in-stock pieces in combo. */
static void algo_combo_prefer_large_partial(Image img, Catalog *catalog, FILE *out,
                                            int *placed, AlgoStats *stats) {
    int W = img.width;
    int H = img.height;

    for (int y = 0; y < H; y++) {
        for (int x = 0; x < W; x++) {
            int idx = y * W + x;
            if (placed[idx]) continue;
            BestPiece bp = find_best_piece_combo(img, x, y, placed, catalog);
            if (bp.index < 0) continue;
            update_stock(catalog, bp.index, stats);
            emit_piece(out, &catalog->bricks[bp.index], x, y, bp.rot);
            stats->price += catalog->bricks[bp.index].price;
            stats->error += bp.error;
            mark_region(placed, W, x, y, bp.w, bp.h);
        }
    }
}

/* Partial 2x2/4x2 placement used by combo. */
static void algo_blocks2x2_partial(Image img, Catalog *catalog, FILE *out,
                                   int threshold, int *placed, AlgoStats *stats) {
    int W = img.width;
    int H = img.height;
    int maxBlocks = (W / 2) * (H / 2);
    Block2x2 *blocks = malloc(sizeof(Block2x2) * maxBlocks);
    int blockCount = 0;

    if (!blocks) {
        perror("Memory allocation failed");
        exit(1);
    }

    for (int y = 0; y + 1 < H; y += 2) {
        for (int x = 0; x + 1 < W; x += 2) {
            if (!region_is_free(placed, W, H, x, y, 2, 2)) continue;
            RegionData rd = avg_and_var(img, x, y, 2, 2);
            int maxd = (int)block_error(img, x, y, 2, 2, rd.averageColor);
            if (maxd <= threshold) {
                blocks[blockCount].x = x;
                blocks[blockCount].y = y;
                blocks[blockCount].avg = rd.averageColor;
                blocks[blockCount].used = 0;
                blockCount++;
            }
        }
    }

    for (int i = 0; i < blockCount; i++) {
        if (blocks[i].used) continue;
        int x = blocks[i].x;
        int y = blocks[i].y;

        int paired = 0;
        for (int j = i + 1; j < blockCount; j++) {
            if (blocks[j].used) continue;
            if (blocks[j].y == y && blocks[j].x == x + 2) {
                if (!region_is_free(placed, W, H, x, y, 4, 2)) continue;
                int diff = compare_colors(blocks[i].avg, blocks[j].avg);
                if (diff <= threshold) {
                    int rot = 0;
                    BestMatch bm = find_best_match_rot(blocks[i].avg, 4, 2, *catalog, &rot);
                    if (bm.index >= 0) {
                        update_stock(catalog, bm.index, stats);
                        emit_piece(out, &catalog->bricks[bm.index], x, y, rot);
                        stats->price += catalog->bricks[bm.index].price;
                        stats->error += block_error(img, x, y, 4, 2, catalog->bricks[bm.index].color);
                        blocks[i].used = blocks[j].used = 1;
                        paired = 1;
                        mark_region(placed, W, x, y, 4, 2);
                        break;
                    }
                }
            }
            if (blocks[j].x == x && blocks[j].y == y + 2) {
                if (!region_is_free(placed, W, H, x, y, 2, 4)) continue;
                int diff = compare_colors(blocks[i].avg, blocks[j].avg);
                if (diff <= threshold) {
                    int rot = 0;
                    BestMatch bm = find_best_match_rot(blocks[i].avg, 2, 4, *catalog, &rot);
                    if (bm.index >= 0) {
                        update_stock(catalog, bm.index, stats);
                        emit_piece(out, &catalog->bricks[bm.index], x, y, rot);
                        stats->price += catalog->bricks[bm.index].price;
                        stats->error += block_error(img, x, y, 2, 4, catalog->bricks[bm.index].color);
                        blocks[i].used = blocks[j].used = 1;
                        paired = 1;
                        mark_region(placed, W, x, y, 2, 4);
                        break;
                    }
                }
            }
        }
        if (paired) continue;
    }

    for (int i = 0; i < blockCount; i++) {
        if (blocks[i].used) continue;
        int x = blocks[i].x;
        int y = blocks[i].y;
        if (!region_is_free(placed, W, H, x, y, 2, 2)) continue;
        BestMatch bm = find_best_match(blocks[i].avg, 2, 2, *catalog);
        if (bm.index >= 0) {
            update_stock(catalog, bm.index, stats);
            emit_piece(out, &catalog->bricks[bm.index], x, y, 0);
            stats->price += catalog->bricks[bm.index].price;
            stats->error += block_error(img, x, y, 2, 2, catalog->bricks[bm.index].color);
            blocks[i].used = 1;
            mark_region(placed, W, x, y, 2, 2);
        }
    }

    free(blocks);
}

/* Partial 2x1/1x2 placement used by combo. */
static void algo_match2x1_partial(Image img, Catalog *catalog, FILE *out,
                                  int *placed, AlgoStats *stats) {
    int W = img.width;
    int H = img.height;

    for (int y = 0; y < H; y++) {
        for (int x = 0; x < W; x++) {
            int idx = y * W + x;
            if (placed[idx]) continue;

            int bestNeighbor = -1;
            int bestRot = 0;
            int bestDiff = INT_MAX;
            BestMatch bestBrick = { -1, INT_MAX };

            if (x + 1 < W && !placed[idx + 1]) {
                RGBValues c1 = img.pixels[y * img.canvasDims + x];
                RGBValues c2 = img.pixels[y * img.canvasDims + (x + 1)];
                RGBValues avg = {
                    .r = (c1.r + c2.r) / 2,
                    .g = (c1.g + c2.g) / 2,
                    .b = (c1.b + c2.b) / 2
                };
                int rot = 0;
                BestMatch bm = find_best_match_rot(avg, 2, 1, *catalog, &rot);
                if (bm.index >= 0 && bm.diff < bestDiff) {
                    bestNeighbor = idx + 1;
                    bestRot = rot;
                    bestDiff = bm.diff;
                    bestBrick = bm;
                }
            }

            if (y + 1 < H && !placed[idx + W]) {
                RGBValues c1 = img.pixels[y * img.canvasDims + x];
                RGBValues c2 = img.pixels[(y + 1) * img.canvasDims + x];
                RGBValues avg = {
                    .r = (c1.r + c2.r) / 2,
                    .g = (c1.g + c2.g) / 2,
                    .b = (c1.b + c2.b) / 2
                };
                int rot = 0;
                BestMatch bm = find_best_match_rot(avg, 1, 2, *catalog, &rot);
                if (bm.index >= 0 && bm.diff < bestDiff) {
                    bestNeighbor = idx + W;
                    bestRot = rot;
                    bestDiff = bm.diff;
                    bestBrick = bm;
                }
            }

            if (bestNeighbor >= 0 && bestBrick.index >= 0) {
                update_stock(catalog, bestBrick.index, stats);
                emit_piece(out, &catalog->bricks[bestBrick.index], x, y, bestRot);
                stats->price += catalog->bricks[bestBrick.index].price;
                stats->error += block_error(img, x, y,
                                            (bestNeighbor == idx + 1) ? 2 : 1,
                                            (bestNeighbor == idx + 1) ? 1 : 2,
                                            catalog->bricks[bestBrick.index].color);
                placed[idx] = 1;
                placed[bestNeighbor] = 1;
                continue;
            }
        }
    }
}

/* Partial 1x1 placement used by combo. */
static void algo_1x1_partial(Image img, Catalog *catalog, FILE *out,
                             int *placed, AlgoStats *stats) {
    int W = img.width;
    int H = img.height;
    for (int y = 0; y < H; y++) {
        for (int x = 0; x < W; x++) {
            int idx = y * W + x;
            if (placed[idx]) continue;
            RGBValues p = img.pixels[y * img.canvasDims + x];
            BestMatch bm = find_best_match(p, 1, 1, *catalog);
            if (bm.index >= 0) {
                update_stock(catalog, bm.index, stats);
                emit_piece(out, &catalog->bricks[bm.index], x, y, 0);
                stats->price += catalog->bricks[bm.index].price;
                stats->error += (double)compare_colors(p, catalog->bricks[bm.index].color);
                placed[idx] = 1;
            }
        }
    }
}

/* Combined algorithm: 2x2/2x1 pass, prefer-large pass, then 1x1. */
static void algo_combo(Image img, Catalog catalog, const char* out_name,
                       int threshold, AlgoStats *stats) {
    FILE* out = fopen(out_name, "w");
    if (!out) {
        perror("Failed to open output file");
        exit(1);
    }

    int W = img.width;
    int H = img.height;
    int *placed = calloc(W * H, sizeof(int));
    if (!placed) {
        perror("Memory allocation failed");
        exit(1);
    }

    algo_blocks2x2_partial(img, &catalog, out, threshold, placed, stats);
    algo_match2x1_partial(img, &catalog, out, placed, stats);
    algo_combo_prefer_large_partial(img, &catalog, out, placed, stats);
    algo_1x1_partial(img, &catalog, out, placed, stats);

    fclose(out);
    make_order_file(catalog, "order_combo.txt");
    free(placed);
}

/* Greedy full-catalog tiler (any size in stock). */
static void algo_any(Image img, Catalog catalog, const char* out_name, AlgoStats *stats) {
    FILE* out = fopen(out_name, "w");
    if (!out) {
        perror("Failed to open output file");
        exit(1);
    }

    int W = img.width;
    int H = img.height;
    int *placed = calloc(W * H, sizeof(int));
    if (!placed) {
        perror("Memory allocation failed");
        exit(1);
    }

    for (int y = 0; y < H; y++) {
        for (int x = 0; x < W; x++) {
            int idx = y * W + x;
            if (placed[idx]) continue;
            BestPiece bp = find_best_piece_any(img, x, y, placed, &catalog);
            if (bp.index < 0) continue;
            update_stock(&catalog, bp.index, stats);
            emit_piece(out, &catalog.bricks[bp.index], x, y, bp.rot);
            stats->price += catalog.bricks[bp.index].price;
            stats->error += bp.error;
            mark_region(placed, W, x, y, bp.w, bp.h);
        }
    }

    fclose(out);
    make_order_file(catalog, "order_any.txt");
    free(placed);
}

/* Greedy tiler that ignores stock limits. */
static void algo_any_nostock(Image img, Catalog catalog, const char* out_name, AlgoStats *stats) {
    FILE* out = fopen(out_name, "w");
    if (!out) {
        perror("Failed to open output file");
        exit(1);
    }

    int W = img.width;
    int H = img.height;
    int *placed = calloc(W * H, sizeof(int));
    if (!placed) {
        perror("Memory allocation failed");
        exit(1);
    }

    for (int y = 0; y < H; y++) {
        for (int x = 0; x < W; x++) {
            int idx = y * W + x;
            if (placed[idx]) continue;
            BestPiece bp = find_best_piece_any_nostock(img, x, y, placed, &catalog);
            if (bp.index < 0) continue;
            update_stock(&catalog, bp.index, stats);
            emit_piece(out, &catalog.bricks[bp.index], x, y, bp.rot);
            stats->price += catalog.bricks[bp.index].price;
            stats->error += bp.error;
            mark_region(placed, W, x, y, bp.w, bp.h);
        }
    }

    fclose(out);
    make_order_file(catalog, "order_any_nostock.txt");
    free(placed);
}

/* Parse catalog format CLI flag. */
static CatalogFormat parse_catalog_format(const char *arg) {
    if (strcmp(arg, "nach") == 0) return CAT_NACH;
    if (strcmp(arg, "kadi") == 0) return CAT_KADI;
    if (strcmp(arg, "helder") == 0) return CAT_HELDER;
    if (strcmp(arg, "matheo") == 0) return CAT_MATHEO;
    return CAT_AUTO;
}

/* Parse image format CLI flag. */
static ImageFormat parse_image_format(const char *arg) {
    if (strcmp(arg, "dim") == 0) return IMG_DIM;
    if (strcmp(arg, "matrix") == 0) return IMG_MATRIX;
    return IMG_AUTO;
}

/* Print stats for an algorithm run. */
static void print_stats(const AlgoStats *stats, const char *outPath) {
    printf("%s %.2f %.0f %d\n", outPath, stats->price, stats->error, stats->ruptures);
}

/* CLI usage helper. */
static void print_usage(const char *prog) {
    fprintf(stderr,
        "Usage: %s <image> <catalog> <algo> <output> [threshold] [--catalog=fmt] [--image=fmt]\n"
        "Algos: 1x1 | match2x1 | blocks2x2 | quadtree | combo | any | any_nostock | cheap | all\n"
        "Catalog formats: auto | nach | kadi | helder | matheo\n"
        "Image formats: auto | dim | matrix\n"
        "Threshold used for quadtree/blocks2x2/cheap (default 500)\n",
        prog);
}

/* Entry point: parse args, run algorithm, emit outputs. */
int main(int argc, char *argv[]) {
    if (argc < 5) {
        print_usage(argv[0]);
        return 1;
    }

    const char* imagePath = argv[1];
    const char* catalogPath = argv[2];
    const char* algo = argv[3];
    const char* outPath = argv[4];
    int threshold = 500;
    int argi = 5;

    if (argc >= 6 && strncmp(argv[5], "--", 2) != 0) {
        threshold = atoi(argv[5]);
        argi = 6;
    }

    CatalogFormat catFmt = CAT_AUTO;
    ImageFormat imgFmt = IMG_AUTO;
    for (int i = argi; i < argc; i++) {
        if (strncmp(argv[i], "--catalog=", 10) == 0) {
            catFmt = parse_catalog_format(argv[i] + 10);
        } else if (strncmp(argv[i], "--image=", 8) == 0) {
            imgFmt = parse_image_format(argv[i] + 8);
        }
    }

    Image img = load_image_auto(imagePath, imgFmt);

    if (strcmp(algo, "1x1") == 0) {
        AlgoStats stats = {0};
        Catalog catalog = load_catalog_auto(catalogPath, catFmt);
        algo_1x1(img, catalog, outPath, &stats);
        print_stats(&stats, outPath);
        free(catalog.bricks);
    } else if (strcmp(algo, "match2x1") == 0) {
        AlgoStats stats = {0};
        Catalog catalog = load_catalog_auto(catalogPath, catFmt);
        algo_match2x1(img, catalog, outPath, &stats);
        print_stats(&stats, outPath);
        free(catalog.bricks);
    } else if (strcmp(algo, "blocks2x2") == 0) {
        AlgoStats stats = {0};
        Catalog catalog = load_catalog_auto(catalogPath, catFmt);
        algo_blocks2x2(img, catalog, outPath, threshold, &stats);
        print_stats(&stats, outPath);
        free(catalog.bricks);
    } else if (strcmp(algo, "quadtree") == 0) {
        AlgoStats stats = {0};
        Catalog catalog = load_catalog_auto(catalogPath, catFmt);
        algo_quadtree(img, catalog, outPath, threshold, &stats);
        print_stats(&stats, outPath);
        free(catalog.bricks);
    } else if (strcmp(algo, "combo") == 0) {
        AlgoStats stats = {0};
        Catalog catalog = load_catalog_auto(catalogPath, catFmt);
        algo_combo(img, catalog, outPath, threshold, &stats);
        print_stats(&stats, outPath);
        free(catalog.bricks);
    } else if (strcmp(algo, "any") == 0) {
        AlgoStats stats = {0};
        Catalog catalog = load_catalog_auto(catalogPath, catFmt);
        algo_any(img, catalog, outPath, &stats);
        print_stats(&stats, outPath);
        free(catalog.bricks);
    } else if (strcmp(algo, "any_nostock") == 0) {
        AlgoStats stats = {0};
        Catalog catalog = load_catalog_auto(catalogPath, catFmt);
        algo_any_nostock(img, catalog, outPath, &stats);
        print_stats(&stats, outPath);
        free(catalog.bricks);
    } else if (strcmp(algo, "cheap") == 0) {
        AlgoStats stats = {0};
        Catalog catalog = load_catalog_auto(catalogPath, catFmt);
        algo_1x1_price_bias(img, catalog, outPath, threshold, &stats);
        print_stats(&stats, outPath);
        free(catalog.bricks);
    } else if (strcmp(algo, "all") == 0) {
        char out1[256], out2[256], out3[256], out4[256], out5[256], out6[256], out7[256], out8[256];
        snprintf(out1, sizeof(out1), "%s_1x1.txt", outPath);
        snprintf(out2, sizeof(out2), "%s_match2x1.txt", outPath);
        snprintf(out3, sizeof(out3), "%s_blocks2x2.txt", outPath);
        snprintf(out4, sizeof(out4), "%s_quadtree.txt", outPath);
        snprintf(out5, sizeof(out5), "%s_combo.txt", outPath);
        snprintf(out6, sizeof(out6), "%s_any.txt", outPath);
        snprintf(out7, sizeof(out7), "%s_any_nostock.txt", outPath);
        snprintf(out8, sizeof(out8), "%s_cheap.txt", outPath);

        AlgoStats stats1 = {0};
        Catalog catalog1 = load_catalog_auto(catalogPath, catFmt);
        algo_1x1(img, catalog1, out1, &stats1);
        print_stats(&stats1, out1);
        free(catalog1.bricks);

        AlgoStats stats2 = {0};
        Catalog catalog2 = load_catalog_auto(catalogPath, catFmt);
        algo_match2x1(img, catalog2, out2, &stats2);
        print_stats(&stats2, out2);
        free(catalog2.bricks);

        AlgoStats stats3 = {0};
        Catalog catalog3 = load_catalog_auto(catalogPath, catFmt);
        algo_blocks2x2(img, catalog3, out3, threshold, &stats3);
        print_stats(&stats3, out3);
        free(catalog3.bricks);

        AlgoStats stats4 = {0};
        Catalog catalog4 = load_catalog_auto(catalogPath, catFmt);
        algo_quadtree(img, catalog4, out4, threshold, &stats4);
        print_stats(&stats4, out4);
        free(catalog4.bricks);

        AlgoStats stats5 = {0};
        Catalog catalog5 = load_catalog_auto(catalogPath, catFmt);
        algo_combo(img, catalog5, out5, threshold, &stats5);
        print_stats(&stats5, out5);
        free(catalog5.bricks);

        AlgoStats stats6 = {0};
        Catalog catalog6 = load_catalog_auto(catalogPath, catFmt);
        algo_any(img, catalog6, out6, &stats6);
        print_stats(&stats6, out6);
        free(catalog6.bricks);

        AlgoStats stats7 = {0};
        Catalog catalog7 = load_catalog_auto(catalogPath, catFmt);
        algo_any_nostock(img, catalog7, out7, &stats7);
        print_stats(&stats7, out7);
        free(catalog7.bricks);

        AlgoStats stats8 = {0};
        Catalog catalog8 = load_catalog_auto(catalogPath, catFmt);
        algo_1x1_price_bias(img, catalog8, out8, threshold, &stats8);
        print_stats(&stats8, out8);
        free(catalog8.bricks);
    } else {
        print_usage(argv[0]);
        free(img.pixels);
        return 1;
    }

    free(img.pixels);
    return 0;
}
