#include <stdio.h>
#include <stdlib.h>
#include "matching.h"
#include "lego.h"

// index linéaire à partir de (x,y)
static inline int index_xy(int x, int y, int W){
    return y * W + x;
}

// Cherche une pièce 2x1 de même couleur que (r,g,b), sinon -1
static int find_2x1_same_color(const Catalog *cat, int r, int g, int b){
    for(int i = 0; i < cat->count; i++){
        const Piece *p = &cat->items[i];
        if(p->w == 2 && p->h == 1 &&
           p->r == r && p->g == g && p->b == b){
            return i;
        }
    }
    return -1;
}

int emit_solution_v2(const char *outPath,
                     const Image *im,
                     const Catalog *cat,
                     const PlanV1 *plan,
                     const Matching *M,
                     double *total_price,
                     double *total_quality,
                     int *ruptures)
{
    FILE *out = fopen(outPath, "w");
    if(!out) return -1;

    int W = im->W;
    int H = im->H;

    // Copie locale des stocks pour les décrémenter
    int *stock = malloc(sizeof(int) * cat->count);
    if(!stock){
        fclose(out);
        return -1;
    }
    for(int i=0;i<cat->count;i++){
        stock[i] = cat->items[i].stock;
    }

    double price   = 0.0;
    double quality = 0.0;
    int rupt       = 0;

    // Parcours de toute la grille
    for(int y=0; y < H; y++){
        for(int x=0; x < W; x++){
            int u = index_xy(x, y, W);
            int v = M->mate[u];

            // Cas paire 2x1 (on ne traite la paire qu'une seule fois : v > u)
            if(v > u){
                int vx = v % W;
                int vy = v / W;

                PixelChoice cu = plan->choices[u];
                PixelChoice cv = plan->choices[v];

                // On suppose même couleur (garantie par matching), mais on reste safe
                int r = cu.color_r;
                int g = cu.color_g;
                int b = cu.color_b;

                int idx2 = find_2x1_same_color(cat, r, g, b);

                if(idx2 >= 0){
                    // On a un vrai 2x1
                    stock[idx2]--;
                    if(stock[idx2] < 0) rupt++;

                    int rotation = (vy == y) ? 0 : 90;
                    int sx = (x < vx) ? x : vx;
                    int sy = (y < vy) ? y : vy;

                    fprintf(out, "%s %d %d %d\n",
                            cat->items[idx2].id, sx, sy, rotation);

                    price   += cat->items[idx2].price;
                    quality += cu.err + cv.err;
                }else{
                    // Pas de 2x1 correspondant → on retombe sur 2 x 1x1
                    int idx1u = cu.p1x1_index;
                    int idx1v = cv.p1x1_index;

                    stock[idx1u]--;
                    if(stock[idx1u] < 0) rupt++;
                    stock[idx1v]--;
                    if(stock[idx1v] < 0) rupt++;

                    fprintf(out, "%s %d %d 0\n",
                            cat->items[idx1u].id, x, y);
                    fprintf(out, "%s %d %d 0\n",
                            cat->items[idx1v].id, vx, vy);

                    price   += cat->items[idx1u].price
                             + cat->items[idx1v].price;
                    quality += cu.err + cv.err;
                }
            }
            // Cas pixel non matché (mate[u] == -1) → 1x1 seul
            else if(v == -1){
                PixelChoice cu = plan->choices[u];
                int idx1 = cu.p1x1_index;

                stock[idx1]--;
                if(stock[idx1] < 0) rupt++;

                fprintf(out, "%s %d %d 0\n",
                        cat->items[idx1].id, x, y);

                price   += cat->items[idx1].price;
                quality += cu.err;
            }
            // Cas v >= 0 && v < u : déjà traité quand on était sur u = pair "plus petit index"
            // => on ne fait rien ici.
        }
    }

    free(stock);
    fclose(out);

    if(total_price)   *total_price   = price;
    if(total_quality) *total_quality = quality;
    if(ruptures)      *ruptures      = rupt;

    return 0;
}
