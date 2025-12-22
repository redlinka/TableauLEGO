#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "lego.h"

static int hex2byte(char a, char b){
    int v1 = (a>='0'&&a<='9') ? a-'0' : (a>='A'&&a<='F') ? a-'A'+10 : a-'a'+10;
    int v2 = (b>='0'&&b<='9') ? b-'0' : (b>='A'&&b<='F') ? b-'A'+10 : b-'a'+10;
    return v1*16 + v2;
}

static double sqdiff(int a, int b){
    double d = (double)a - (double)b;
    return d*d;
}

int load_image(const char *path, Image *im){
    FILE *f = fopen(path, "r");
    if(!f) return -1;
    fscanf(f, "%d %d", &im->W, &im->H);

    int N = im->W * im->H;
    im->r = malloc(N);
    im->g = malloc(N);
    im->b = malloc(N);

    for(int i=0;i<N;i++){
        char hex[7];
        fscanf(f, "%6s", hex);
        im->r[i] = hex2byte(hex[0],hex[1]);
        im->g[i] = hex2byte(hex[2],hex[3]);
        im->b[i] = hex2byte(hex[4],hex[5]);
    }
    fclose(f);
    return 0;
}

void free_image(Image *im){
    free(im->r); free(im->g); free(im->b);
}

int load_catalog(const char *path, Catalog *cat){
    FILE *f = fopen(path, "r");
    if(!f) return -1;

    cat->items = malloc(sizeof(Piece) * MAX_PIECES);
    cat->count = 0;

    while(cat->count < MAX_PIECES){
        Piece p;
        int rd = fscanf(f, "%s %d %d %d %d %d %lf %d",
            p.id, &p.w, &p.h, &p.r, &p.g, &p.b, &p.price, &p.stock);
        if(rd == EOF) break;
        if(rd != 8) return -1;
        cat->items[cat->count++] = p;
    }
    fclose(f);
    return cat->count;
}

void free_catalog(Catalog *cat){
    free(cat->items);
}

int build_v1_plan(const Image *im, const Catalog *cat, PlanV1 *plan, double *total_error){
    int N = im->W * im->H;
    plan->choices = calloc(N, sizeof(PixelChoice));
    plan->im = im;

    double sum = 0.0;
    for(int i=0;i<N;i++){
        int br = im->r[i], bg = im->g[i], bb = im->b[i];
        int best = -1; double best_err = 1e30;

        for(int j=0; j < cat->count; j++){
            Piece *p = &cat->items[j];
            if(p->w == 1 && p->h == 1){
                double e = sqdiff(p->r,br)+sqdiff(p->g,bg)+sqdiff(p->b,bb);
                if(best < 0 || e < best_err){
                    best = j; best_err = e;
                }
            }
        }

        plan->choices[i].color_r = cat->items[best].r;
        plan->choices[i].color_g = cat->items[best].g;
        plan->choices[i].color_b = cat->items[best].b;
        plan->choices[i].p1x1_index = best;
        plan->choices[i].err = best_err;
        sum += best_err;
    }
    *total_error = sum;
    return 0;
}
