#ifndef MATCHING_H
#define MATCHING_H

#include "lego.h"

int emit_solution_v2(const char *outPath,
                     const Image *im,
                     const Catalog *cat,
                     const PlanV1 *plan,
                     const Matching *M,
                     double *total_price,
                     double *total_quality,
                     int *ruptures);


typedef struct {
    int W, H;
    int *mate;
} Matching;

void matching_init(Matching *M, int W, int H);
void matching_free(Matching *M);

int build_matching_2x1(const Catalog*, const PlanV1*, Matching*);
int emit_solution_v2(const char*, const Image*, const Catalog*, const PlanV1*, const Matching*,
                     double*, double*, int*);

#endif
