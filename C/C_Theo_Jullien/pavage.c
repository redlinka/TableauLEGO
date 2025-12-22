#include <stdio.h>
#include <stdlib.h>  // pour malloc et free
#include <string.h>  // pour strcpy

#define MAX_LINE 256

/* Modes de pavage */
#define MODE_USE_OUT_OF_STOCK 0   // utilise même si stock = 0 (et compte en rupture)
#define MODE_ONLY_IN_STOCK   1   // utilise uniquement les pièces en stock
#define CRITERIA_QUALITY 0
#define CRITERIA_PRICE   1

/* Structures */
typedef struct {
    unsigned char r, g, b;
} Pixel;

typedef struct {
    int shapeIndex;
    int colorIndex;
    int price;
    int stock;
} Piece;

typedef Piece *Pieces;

/* a voir si je garde cette structure*/
typedef struct {
    int W;
    int H;
    //Pixel* pixels;
} Image;

Pixel hexaToPixel(char *hexa) {
    Pixel p;
    sscanf(hexa, "%2hhx%2hhx%2hhx", &p.r, &p.g, &p.b);
    return p;
}

// calcule la difference entre 2 pixels
int colorError(Pixel pixel1, Pixel pixel2) {
    int d = 
        (pixel1.r - pixel2.r) * (pixel1.r - pixel2.r) 
        + (pixel1.g - pixel2.g) * (pixel1.g - pixel2.g)
        + (pixel1.b - pixel2.b) * (pixel1.b - pixel2.b);
    return d;
}

Piece *findBetterPiece(Pixel p, Pieces pieces, Pixel *colors, int n, int stockMode, int criteria) {
    if (pieces == NULL || n <= 0) {
        printf("Error : no pieces\n");
        return NULL;
    }
    Piece *best = NULL;
    int bestScore = 0x7FFFFFFF;
    for (int i = 0; i < n; i++) {

        if (stockMode == MODE_ONLY_IN_STOCK && pieces[i].stock <= 0)
            continue;

        int score;

        if (criteria == CRITERIA_QUALITY) {
            score = colorError(p, colors[pieces[i].colorIndex]);
        } else { // CRITERIA_PRICE
            score = pieces[i].price;
        }

        if (!best || score < bestScore) {
            best = &pieces[i];
            bestScore = score;
        }
    }
    return best;
}

int load_pieces(
    const char *filename,
    int *nbShapes,
    int *nbColors,
    int *nbPieces,
    char ***shapes,
    Pixel **colors,
    Piece **pieces
) {
    FILE *f = fopen(filename, "r");
    if (!f) return 1;

    fscanf(f, "%d %d %d", nbShapes, nbColors, nbPieces);

    *shapes = malloc(*nbShapes * sizeof(char *));
    for (int i = 0; i < *nbShapes; i++) {
        char buffer[32];
        fscanf(f, "%s", buffer);
        (*shapes)[i] = strdup(buffer);
    }

    *colors = malloc(*nbColors * sizeof(Pixel));
    for (int i = 0; i < *nbColors; i++) {
        char hexa[7];
        fscanf(f, "%6s", hexa);
        (*colors)[i] = hexaToPixel(hexa);
    }

    *pieces = malloc(*nbPieces * sizeof(Piece));
    for (int i = 0; i < *nbPieces; i++) {
        int s, c, price, stock;
        fscanf(f, "%d/%d %d %d", &s, &c, &price, &stock);
        (*pieces)[i].shapeIndex = s;
        (*pieces)[i].colorIndex = c;
        (*pieces)[i].price = price;
        (*pieces)[i].stock = stock;
    }

    fclose(f);
    return 0;
}

int load_image_size(FILE *f, Image *img) {
    return fscanf(f, "%d %d", &img->W, &img->H) == 2;
}

int pavage_1x1(
    FILE *fin,
    FILE *fout,
    Image img,
    Piece *pieces,
    int nbPieces,
    Pixel *colors,
    char **shapes,
    int stockMode,
    int criteria,
    int *totalPlaced,
    int *totalPrice,
    int *nbOutOfStock
) {
    Pixel pixel;
    char hexa[7];

    for (int y = 0; y < img.H; y++) {
        for (int x = 0; x < img.W; x++) {

            if (fscanf(fin, "%6s", hexa) != 1) return 1;
            pixel = hexaToPixel(hexa);

            Piece *best = findBetterPiece(
                pixel,
                pieces,
                colors,
                nbPieces,
                stockMode,
                criteria
            );

            if (!best) continue;

            /* --- GESTION DES STOCKS --- */
            if (best->stock > 0) {
                best->stock--;
            } else {
                (*nbOutOfStock)++;
            }

            (*totalPlaced)++;
            (*totalPrice) += best->price;

            fprintf(
                fout,
                "%s/%02x%02x%02x %d %d 0\n",
                shapes[best->shapeIndex],
                colors[best->colorIndex].r,
                colors[best->colorIndex].g,
                colors[best->colorIndex].b,
                x,
                y
            );
        }
    }
    return 0;
}

int process(
    const char *imagefile,
    const char *outfile,
    Piece *pieces,
    int nbPieces,
    Pixel *colors,
    char **shapes
) {
    FILE *fin = fopen(imagefile, "r");
    if (!fin) return 1;

    FILE *fout = fopen(outfile, "w");
    if (!fout) {
        fclose(fin);
        return 1;
    }

    Image img;
    if (!load_image_size(fin, &img)) {
        fclose(fin);
        fclose(fout);
        return 1;
    }

    int totalPrice = 0;
    int totalPlaced = 0;
    int nbOutOfStock = 0;

    long headerPos = ftell(fout);
    fprintf(fout, "%20s\n", "");

    /* ==============================
       CHOIX DES MODES ICI
       ============================== */

    int stockMode = MODE_USE_OUT_OF_STOCK;   // ou MODE_ONLY_IN_STOCK
    int criteria  = CRITERIA_QUALITY;        // ou CRITERIA_PRICE

    pavage_1x1(
        fin,
        fout,
        img,
        pieces,
        nbPieces,
        colors,
        shapes,
        stockMode,
        criteria,
        &totalPlaced,
        &totalPrice,
        &nbOutOfStock
    );

    fseek(fout, headerPos, SEEK_SET);
    fprintf(fout, "%d %d %d\n", totalPlaced, totalPrice, nbOutOfStock);

    fclose(fin);
    fclose(fout);
    return 0;
}

int main(int argc, char **argv) {
    if (argc != 4) {
        printf("Usage: %s briques.txt image.txt output.txt\n", argv[0]);
        return 1;
    }

    int nbShapes, nbColors, nbPieces;
    char **shapes;
    Pixel *colors;
    Piece *pieces;

    if (load_pieces(argv[1], &nbShapes, &nbColors, &nbPieces, &shapes, &colors, &pieces)) {
        printf("Error loading pieces file\n");
        return 1;
    }

    /* --- Choix des paramètres de pavage --- */
    int stockMode  = MODE_ONLY_IN_STOCK; // MODE_USE_OUT_OF_STOCK ou MODE_ONLY_IN_STOCK
    int criteria   = CRITERIA_QUALITY;      // CRITERIA_QUALITY ou CRITERIA_PRICE

    if (process(argv[2], argv[3], pieces, nbPieces, colors, shapes)) {
        printf("Processing error\n");
        for (int i = 0; i < nbShapes; i++) free(shapes[i]);
        free(shapes);
        free(colors);
        free(pieces);
        return 1;
    }

    /* --- Libération mémoire --- */
    for (int i = 0; i < nbShapes; i++) free(shapes[i]);
    free(shapes);
    free(colors);
    free(pieces);

    return 0;
}
