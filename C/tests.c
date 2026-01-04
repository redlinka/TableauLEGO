#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>

#include "MergeFile.c"

static int tests_run = 0;
static int tests_failed = 0;

#define ASSERT_TRUE(expr) do { \
    tests_run++; \
    if (!(expr)) { \
        tests_failed++; \
        fprintf(stderr, "FAIL: %s:%d: %s\n", __FILE__, __LINE__, #expr); \
    } \
} while (0)

static void test_biggest_pow_2(void) {
    ASSERT_TRUE(biggest_pow_2(1) == 1);
    ASSERT_TRUE(biggest_pow_2(2) == 2);
    ASSERT_TRUE(biggest_pow_2(3) == 4);
    ASSERT_TRUE(biggest_pow_2(16) == 16);
    ASSERT_TRUE(biggest_pow_2(17) == 32);
}

static void test_hex_to_RGB(void) {
    RGBValues out;
    ASSERT_TRUE(hex_to_RGB("FFA07A", &out) == 1);
    ASSERT_TRUE(out.r == 255 && out.g == 160 && out.b == 122);
    ASSERT_TRUE(hex_to_RGB("#00FF00", &out) == 1);
    ASSERT_TRUE(out.r == 0 && out.g == 255 && out.b == 0);
    ASSERT_TRUE(hex_to_RGB("GG", &out) == 0);
}

static void test_parse_holes(void) {
    int holes[MAX_HOLE_NUMBER];
    ASSERT_TRUE(parse_holes("0123", holes) == 1);
    ASSERT_TRUE(holes[0] == 0 && holes[1] == 1 && holes[2] == 2 && holes[3] == 3);
    ASSERT_TRUE(parse_holes("-1", holes) == 1);
    ASSERT_TRUE(holes[0] == -1 && holes[1] == -1 && holes[2] == -1 && holes[3] == -1);
    ASSERT_TRUE(parse_holes("01234", holes) == 0);
}

static void test_avg_and_var(void) {
    Image img;
    img.width = 2;
    img.height = 1;
    img.canvasDims = 2;
    img.pixels = calloc(img.canvasDims * img.canvasDims, sizeof(RGBValues));
    ASSERT_TRUE(img.pixels != NULL);
    img.pixels[0] = (RGBValues){0, 0, 0};
    img.pixels[1] = (RGBValues){255, 0, 0};

    RegionData rd = avg_and_var(img, 0, 0, 2, 1);
    ASSERT_TRUE(rd.count == 2);
    ASSERT_TRUE(rd.averageColor.r == 127 && rd.averageColor.g == 0 && rd.averageColor.b == 0);
    ASSERT_TRUE(fabs(rd.variance - 16256.25) < 1e-6);

    free(img.pixels);
}

static void fill_image_color(Image* img, int w, int h, RGBValues color) {
    img->width = w;
    img->height = h;
    img->canvasDims = biggest_pow_2(MAX(w, h));
    img->pixels = calloc(img->canvasDims * img->canvasDims, sizeof(RGBValues));
    for (int i = 0; i < img->canvasDims * img->canvasDims; i++) {
        img->pixels[i] = NULL_PIXEL;
    }
    for (int y = 0; y < h; y++) {
        for (int x = 0; x < w; x++) {
            img->pixels[y * img->canvasDims + x] = color;
        }
    }
}

static void test_solve_4x2_mix_prefers_4x2(void) {
    Image img;
    RGBValues color = {10, 20, 30};
    fill_image_color(&img, 4, 2, color);

    Catalog cat;
    cat.size = 2;
    cat.bricks = calloc(cat.size, sizeof(Brick));
    ASSERT_TRUE(cat.bricks != NULL);

    snprintf(cat.bricks[0].name, sizeof(cat.bricks[0].name), "4-2/0A141E");
    cat.bricks[0].width = 4;
    cat.bricks[0].height = 2;
    cat.bricks[0].color = color;
    cat.bricks[0].price = 1.0f;
    cat.bricks[0].stock = 1;

    snprintf(cat.bricks[1].name, sizeof(cat.bricks[1].name), "1-1/0A141E");
    cat.bricks[1].width = 1;
    cat.bricks[1].height = 1;
    cat.bricks[1].color = color;
    cat.bricks[1].price = 0.1f;
    cat.bricks[1].stock = 10;

    Solution sol;
    ASSERT_TRUE(solve_4x2_mix(&img, &cat, &sol, STOCK_STRICT) == 1);
    ASSERT_TRUE(sol.count == 1);
    ASSERT_TRUE(sol.placements[0].x == 0 && sol.placements[0].y == 0);
    ASSERT_TRUE(strcmp(sol.placements[0].brick_name, "4-2/0A141E") == 0);

    solution_free(&sol);
    free(cat.bricks);
    free(img.pixels);
}

static void test_solve_4x2_mix_fallback_1x1(void) {
    Image img;
    RGBValues color = {50, 60, 70};
    fill_image_color(&img, 2, 1, color);

    Catalog cat;
    cat.size = 1;
    cat.bricks = calloc(cat.size, sizeof(Brick));
    ASSERT_TRUE(cat.bricks != NULL);

    snprintf(cat.bricks[0].name, sizeof(cat.bricks[0].name), "1-1/323C46");
    cat.bricks[0].width = 1;
    cat.bricks[0].height = 1;
    cat.bricks[0].color = color;
    cat.bricks[0].price = 0.1f;
    cat.bricks[0].stock = 10;

    Solution sol;
    ASSERT_TRUE(solve_4x2_mix(&img, &cat, &sol, STOCK_STRICT) == 1);
    ASSERT_TRUE(sol.count == 2);

    solution_free(&sol);
    free(cat.bricks);
    free(img.pixels);
}

static void test_load_catalog(void) {
    const char* path = "test_catalog.txt";
    FILE* f = fopen(path, "w");
    ASSERT_TRUE(f != NULL);
    fprintf(f, "2\n");
    fprintf(f, "1,1,-1,0A141E,0.10,5\n");
    fprintf(f, "4,2,0123,FFFFFF,1.25,2\n");
    fclose(f);

    Catalog cat;
    ASSERT_TRUE(load_catalog(path, &cat) == 1);
    ASSERT_TRUE(cat.size == 2);
    ASSERT_TRUE(cat.bricks[0].width == 1 && cat.bricks[0].height == 1);
    ASSERT_TRUE(cat.bricks[0].stock == 5);
    ASSERT_TRUE(cat.bricks[1].width == 4 && cat.bricks[1].height == 2);
    ASSERT_TRUE(cat.bricks[1].stock == 2);

    free(cat.bricks);
    remove(path);
}

static void test_quadtree_no_split_on_low_variance(void) {
    Image img;
    RGBValues color = {20, 30, 40};
    fill_image_color(&img, 2, 2, color);

    Catalog cat;
    cat.size = 1;
    cat.bricks = calloc(cat.size, sizeof(Brick));
    ASSERT_TRUE(cat.bricks != NULL);
    snprintf(cat.bricks[0].name, sizeof(cat.bricks[0].name), "2-2/141E28");
    cat.bricks[0].width = 2;
    cat.bricks[0].height = 2;
    cat.bricks[0].color = color;
    cat.bricks[0].price = 1.0f;
    cat.bricks[0].stock = 1;

    Solution sol;
    Node* root = solve_quadtree(&img, &cat, 1000, &sol, STOCK_STRICT, MATCH_COLOR);
    ASSERT_TRUE(root != NULL);
    ASSERT_TRUE(root->is_leaf == 1);
    ASSERT_TRUE(sol.count == 1);

    free_QUADTREE(root);
    solution_free(&sol);
    free(cat.bricks);
    free(img.pixels);
}

static void test_quadtree_split_on_low_threshold(void) {
    Image img;
    RGBValues color = {20, 30, 40};
    fill_image_color(&img, 2, 2, color);

    Catalog cat;
    cat.size = 2;
    cat.bricks = calloc(cat.size, sizeof(Brick));
    ASSERT_TRUE(cat.bricks != NULL);
    snprintf(cat.bricks[0].name, sizeof(cat.bricks[0].name), "2-2/141E28");
    cat.bricks[0].width = 2;
    cat.bricks[0].height = 2;
    cat.bricks[0].color = color;
    cat.bricks[0].price = 1.0f;
    cat.bricks[0].stock = 1;

    snprintf(cat.bricks[1].name, sizeof(cat.bricks[1].name), "1-1/141E28");
    cat.bricks[1].width = 1;
    cat.bricks[1].height = 1;
    cat.bricks[1].color = color;
    cat.bricks[1].price = 0.1f;
    cat.bricks[1].stock = 10;

    Solution sol;
    Node* root = solve_quadtree(&img, &cat, 0, &sol, STOCK_STRICT, MATCH_COLOR);
    ASSERT_TRUE(root != NULL);
    ASSERT_TRUE(root->is_leaf == 0);
    ASSERT_TRUE(sol.count == 4);

    free_QUADTREE(root);
    solution_free(&sol);
    free(cat.bricks);
    free(img.pixels);
}

static void test_stock_strict_vs_relax(void) {
    Catalog cat;
    cat.size = 1;
    cat.bricks = calloc(cat.size, sizeof(Brick));
    ASSERT_TRUE(cat.bricks != NULL);
    cat.bricks[0].stock = 0;

    ASSERT_TRUE(consume_stock(&cat, 0, STOCK_STRICT, NULL) == 0);
    ASSERT_TRUE(cat.bricks[0].stock == 0);

    int breaks = 0;
    ASSERT_TRUE(consume_stock(&cat, 0, STOCK_RELAX, &breaks) == 1);
    ASSERT_TRUE(cat.bricks[0].stock == -1);
    ASSERT_TRUE(breaks == 1);

    free(cat.bricks);
}

static void test_price_bias_choice(void) {
    Catalog cat;
    cat.size = 2;
    cat.bricks = calloc(cat.size, sizeof(Brick));
    ASSERT_TRUE(cat.bricks != NULL);

    RGBValues target = {0, 0, 0};
    cat.bricks[0].width = 2;
    cat.bricks[0].height = 2;
    cat.bricks[0].color = (RGBValues){10, 0, 0}; /* diff 100 */
    cat.bricks[0].price = 2.0f;
    cat.bricks[0].stock = 1;

    cat.bricks[1].width = 2;
    cat.bricks[1].height = 2;
    cat.bricks[1].color = (RGBValues){10, 1, 0}; /* diff 101 */
    cat.bricks[1].price = 1.0f;
    cat.bricks[1].stock = 1;

    BestMatch best = find_best_match_price_bias(target, 2, 2, &cat, STOCK_RELAX, 10);
    ASSERT_TRUE(best.index == 1);

    free(cat.bricks);
}

static void test_solve_4x2_mix_rotation(void) {
    Image img;
    RGBValues color = {5, 15, 25};
    fill_image_color(&img, 2, 4, color);

    Catalog cat;
    cat.size = 2;
    cat.bricks = calloc(cat.size, sizeof(Brick));
    ASSERT_TRUE(cat.bricks != NULL);

    snprintf(cat.bricks[0].name, sizeof(cat.bricks[0].name), "2-4/050F19");
    cat.bricks[0].width = 2;
    cat.bricks[0].height = 4;
    cat.bricks[0].color = color;
    cat.bricks[0].price = 1.0f;
    cat.bricks[0].stock = 1;

    snprintf(cat.bricks[1].name, sizeof(cat.bricks[1].name), "1-1/050F19");
    cat.bricks[1].width = 1;
    cat.bricks[1].height = 1;
    cat.bricks[1].color = color;
    cat.bricks[1].price = 0.1f;
    cat.bricks[1].stock = 10;

    Solution sol;
    ASSERT_TRUE(solve_4x2_mix(&img, &cat, &sol, STOCK_STRICT) == 1);
    ASSERT_TRUE(sol.count == 1);
    ASSERT_TRUE(strcmp(sol.placements[0].brick_name, "2-4/050F19") == 0);

    solution_free(&sol);
    free(cat.bricks);
    free(img.pixels);
}

int main(void) {
    test_biggest_pow_2();
    test_hex_to_RGB();
    test_parse_holes();
    test_avg_and_var();
    test_solve_4x2_mix_prefers_4x2();
    test_solve_4x2_mix_fallback_1x1();
    test_load_catalog();
    test_quadtree_no_split_on_low_variance();
    test_quadtree_split_on_low_threshold();
    test_stock_strict_vs_relax();
    test_price_bias_choice();
    test_solve_4x2_mix_rotation();

    if (tests_failed == 0) {
        printf("All tests passed (%d)\n", tests_run);
        return 0;
    }
    printf("Tests failed: %d/%d\n", tests_failed, tests_run);
    return 1;
}
