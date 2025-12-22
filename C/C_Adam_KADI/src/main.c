#include <stdio.h>
#include <stdlib.h>
#include "lego.h"
#include "matching.h"

// Prototype de emit_solution_v2
int emit_solution_v2(const char *outPath,
                     const Image *im,
                     const Catalog *cat,
                     const PlanV1 *plan,
                     const Matching *M,
                     double *total_price,
                     double *total_quality,
                     int *ruptures);

int main(int argc, char **argv){
    if(argc != 4){
        fprintf(stderr,
            "Usage: %s <pieces.txt> <image.txt> <pavage.out>\n",
            argv[0]);
        return 1;
    }

    const char *pieces_path = argv[1];
    const char *image_path  = argv[2];
    const char *out_path    = argv[3];

    Catalog cat = {0};
    Image im = {0};

    // Chargement du catalogue
    if(load_catalog(pieces_path, &cat) <= 0){
        fprintf(stderr, "Erreur: chargement du catalogue '%s'\n", pieces_path);
        return 1;
    }

    // Chargement de l'image
    if(load_image(image_path, &im) != 0){
        fprintf(stderr, "Erreur: chargement de l'image '%s'\n", image_path);
        free_catalog(&cat);
        return 1;
    }

    // Plan V1 : 1x1 de couleur la plus proche
    PlanV1 plan = {0};
    double total_err_v1 = 0.0;
    if(build_v1_plan(&im, &cat, &plan, &total_err_v1) != 0){
        fprintf(stderr, "Erreur: construction du plan V1\n");
        free_image(&im);
        free_catalog(&cat);
        return 1;
    }

    // Matching 2x1 (version V2)
    Matching M;
    matching_init(&M, im.W, im.H);
    build_matching_2x1(&cat, &plan, &M);

    // Émission finale (pavage + stats)
    double price   = 0.0;
    double quality = 0.0;
    int ruptures   = 0;

    if(emit_solution_v2(out_path, &im, &cat, &plan, &M,
                        &price, &quality, &ruptures) != 0){
        fprintf(stderr, "Erreur: écriture du pavage '%s'\n", out_path);
        matching_free(&M);
        free(plan.choices);
        free_image(&im);
        free_catalog(&cat);
        return 1;
    }

    // Ligne demandée sur stdout : chemin prix qualité ruptures
    printf("%s %.2f %.0f %d\n", out_path, price, quality, ruptures);

    // Libération mémoire
    matching_free(&M);
    free(plan.choices);
    free_image(&im);
    free_catalog(&cat);

    return 0;
}
