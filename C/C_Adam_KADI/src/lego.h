#ifndef LEGO_H
#define LEGO_H

#define MAX_PIECES 512
#define MAX_NAME   48

typedef struct {
    int W, H;
    unsigned char *r, *g, *b;
} Image;

typedef struct {
    char id[MAX_NAME];
    int w, h;
    int r, g, b;
    double price;
    int stock;
} Piece;

typedef struct {
    Piece *items;
    int count;
} Catalog;

typedef struct {
    int color_r, color_g, color_b;
    int p1x1_index;
    double err;
} PixelChoice;

typedef struct {
    const Image *im;
    PixelChoice *choices;
} PlanV1;

int load_image(const char *path, Image *im);
void free_image(Image *im);

int load_catalog(const char *path, Catalog *cat);
void free_catalog(Catalog *cat);

int build_v1_plan(const Image *im, const Catalog *cat, PlanV1 *plan, double *total_error);

#endif
