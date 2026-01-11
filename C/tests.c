/*
 * Compile with: gcc -o run_tests new_tests.c -lm
 * (Ensure MergeFile.c is in the same directory)
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>
#include <assert.h>

/* Define UNIT_TEST to prevent main() collision if MergeFile.c has one */
#define UNIT_TEST 1
#include "MergeFile.c"

/* --- Better Test Harness --- */
static int tests_run = 0;
static int tests_failed = 0;

#define ANSI_RED     "\x1b[31m"
#define ANSI_GREEN   "\x1b[32m"
#define ANSI_RESET   "\x1b[0m"

#define ASSERT_TRUE(expr) do { \
    tests_run++; \
    if (!(expr)) { \
        tests_failed++; \
        fprintf(stderr, ANSI_RED "FAIL: %s:%d: %s\n" ANSI_RESET, __FILE__, __LINE__, #expr); \
        return; \
    } \
} while (0)

#define ASSERT_EQ(actual, expected) do { \
    tests_run++; \
    int a = (actual); int e = (expected); \
    if (a != e) { \
        tests_failed++; \
        fprintf(stderr, ANSI_RED "FAIL: %s:%d: Expected %d, got %d\n" ANSI_RESET, __FILE__, __LINE__, e, a); \
        return; \
    } \
} while (0)

#define ASSERT_STR_EQ(actual, expected) do { \
    tests_run++; \
    const char* a = (actual); const char* e = (expected); \
    if (strcmp(a, e) != 0) { \
        tests_failed++; \
        fprintf(stderr, ANSI_RED "FAIL: %s:%d: Expected '%s', got '%s'\n" ANSI_RESET, __FILE__, __LINE__, e, a); \
        return; \
    } \
} while (0)

/* --- Helper to create temp files for I/O testing --- */
void create_temp_file(const char* name, const char* content) {
    FILE* f = fopen(name, "w");
    if (f) {
        fprintf(f, "%s", content);
        fclose(f);
    }
}

/* =========================================================================
 * TEST CASES
 * ========================================================================= */

/* 1. Utility Robustness: Bad Inputs & Edge Cases */
static void test_utils_robustness(void) {
    printf("[Running] Utils Robustness...\n");

    // biggest_pow_2
    ASSERT_EQ(biggest_pow_2(0), 1);     // Edge case: 0
    ASSERT_EQ(biggest_pow_2(-100), 1);  // Edge case: Negative
    ASSERT_EQ(biggest_pow_2(1), 1);
    ASSERT_EQ(biggest_pow_2(17), 32);
    ASSERT_EQ(biggest_pow_2(1025), 2048);

    // hex_to_RGB
    RGBValues out = {0};
    ASSERT_EQ(hex_to_RGB(NULL, &out), 0);          // NULL input
    ASSERT_EQ(hex_to_RGB("ZZZZZZ", &out), 0);      // Invalid Hex
    ASSERT_EQ(hex_to_RGB("FFF", &out), 0);         // Too short
    ASSERT_EQ(hex_to_RGB("FFFFFFF", &out), 0);     // Too long
    ASSERT_EQ(hex_to_RGB("FFAA11", &out), 1);      // Valid
    ASSERT_EQ(out.r, 255);

    // parse_holes
    int holes[MAX_HOLE_NUMBER];
    ASSERT_EQ(parse_holes(NULL, holes), 0);        // NULL
    ASSERT_EQ(parse_holes("01234", holes), 0);     // Too many holes (MAX is 4)
    ASSERT_EQ(parse_holes("a", holes), 0);         // Non-digit
    ASSERT_EQ(parse_holes("", holes), 0);          // Empty
    ASSERT_EQ(parse_holes("-1", holes), 1);        // Valid "No holes"
    ASSERT_EQ(holes[0], -1);
    ASSERT_EQ(parse_holes("03", holes), 1);        // Valid mixed
    ASSERT_EQ(holes[0], 0);
    ASSERT_EQ(holes[1], 3);
}

/* 2. File I/O: Corrupt and Missing Files */
static void test_file_io_failures(void) {
    printf("[Running] File I/O Failures...\n");

    Catalog cat;
    Image img;

    // Missing files
    ASSERT_EQ(load_catalog("ghost_file.txt", &cat), 0);
    ASSERT_EQ(load_image("ghost_img.txt", &img), 0);

    // Corrupt Catalog: Garbage text
    create_temp_file("bad_cat.txt", "This is not a catalog");
    ASSERT_EQ(load_catalog("bad_cat.txt", &cat), 0); // Should fail, not crash
    remove("bad_cat.txt");

    // Corrupt Catalog: Negative count
    create_temp_file("neg_cat.txt", "-5\n");
    ASSERT_EQ(load_catalog("neg_cat.txt", &cat), 0); // Should fail
    remove("neg_cat.txt");

    // Corrupt Image: Missing dimensions
    create_temp_file("bad_img.txt", "FF00FF");
    ASSERT_EQ(load_image("bad_img.txt", &img), 0);
    remove("bad_img.txt");

    // Corrupt Image: Dimensions 0
    create_temp_file("zero_img.txt", "0 0\nFFFFFF");
    ASSERT_EQ(load_image("zero_img.txt", &img), 0);
    remove("zero_img.txt");
}

/* 3. Logic: Stock Consumption & Relaxed Mode */
static void test_stock_logic(void) {
    printf("[Running] Stock Logic...\n");
    
    Catalog cat;
    cat.size = 1;
    cat.bricks = calloc(1, sizeof(Brick));
    cat.bricks[0].stock = 1;
    
    // Test 1: Consume valid stock
    ASSERT_EQ(consume_stock(&cat, 0, STOCK_STRICT, NULL), 1);
    ASSERT_EQ(cat.bricks[0].stock, 0);

    // Test 2: Consume empty stock (Strict) -> Fail
    ASSERT_EQ(consume_stock(&cat, 0, STOCK_STRICT, NULL), 0);
    ASSERT_EQ(cat.bricks[0].stock, 0); // Should remain 0

    // Test 3: Consume empty stock (Relax) -> Success but negative
    int breaks = 0;
    ASSERT_EQ(consume_stock(&cat, 0, STOCK_RELAX, &breaks), 1);
    ASSERT_EQ(cat.bricks[0].stock, -1);
    ASSERT_EQ(breaks, 1);

    // Test 4: Invalid index
    ASSERT_EQ(consume_stock(&cat, -1, STOCK_STRICT, NULL), 0);
    ASSERT_EQ(consume_stock(&cat, 50, STOCK_STRICT, NULL), 0); // Index OOB check usually relies on caller, but consume checks < 0

    free(cat.bricks);
}

/* 4. Logic: Tiling with Select (Basket Weave) */
static void test_tile_selected_logic(void) {
    printf("[Running] Tile Selected Logic...\n");

    // Create a 4x4 image (pure red)
    Image img;
    img.width = 4; img.height = 4; img.canvasDims = 4;
    img.pixels = calloc(16, sizeof(RGBValues));
    for(int i=0; i<16; i++) img.pixels[i] = (RGBValues){255, 0, 0};

    // Catalog: Only has 2x2 Red brick
    Catalog cat;
    cat.size = 1;
    cat.bricks = calloc(1, sizeof(Brick));
    snprintf(cat.bricks[0].name, 32, "2-2/Red");
    cat.bricks[0].width = 2; cat.bricks[0].height = 2;
    cat.bricks[0].color = (RGBValues){255, 0, 0};
    cat.bricks[0].stock = 100;
    cat.bricks[0].price = 1.0;

    Solution sol;
    
    // Attempt to tile with 2x2 preferred
    ASSERT_EQ(tile_with_selected(&img, &cat, &sol, STOCK_STRICT, 2, 2, 1000.0), 1);
    
    // Should fit exactly 4 bricks (4x4 area / 2x2 brick = 4 bricks)
    ASSERT_EQ(sol.count, 4); 
    ASSERT_STR_EQ(sol.placements[0].brick_name, "2-2/Red");

    solution_free(&sol);

    // Edge case: Tile with dimensions larger than image
    // Should fallback to 1x1 logic or fail if 1x1 not in catalog.
    // Here catalog has no 1x1, so it might fail or return partial.
    // The current implementation returns 0 if fallback fails.
    int res = tile_with_selected(&img, &cat, &sol, STOCK_STRICT, 10, 10, 1000.0);
    ASSERT_EQ(res, 0); // Expected failure because no 1x1 fallback available

    free(cat.bricks);
    free(img.pixels);
}

/* 5. Quadtree: Threshold & Recursion */
static void test_quadtree_recursion(void) {
    printf("[Running] Quadtree Recursion...\n");

    // 4x4 Image: Top-Left quadrant is Black, rest is White
    Image img;
    img.width = 4; img.height = 4; img.canvasDims = 4;
    img.pixels = calloc(16, sizeof(RGBValues));
    for(int y=0; y<4; y++) {
        for(int x=0; x<4; x++) {
            if (x < 2 && y < 2) img.pixels[y*4+x] = (RGBValues){0,0,0};
            else img.pixels[y*4+x] = (RGBValues){255,255,255};
        }
    }

    Catalog cat;
    cat.size = 2;
    cat.bricks = calloc(2, sizeof(Brick));
    // 2x2 Black
    snprintf(cat.bricks[0].name, 32, "2-2/Black");
    cat.bricks[0].width = 2; cat.bricks[0].height = 2;
    cat.bricks[0].color = (RGBValues){0,0,0};
    cat.bricks[0].stock = 10;
    cat.bricks[0].price = 1.0;
    // 2x2 White
    snprintf(cat.bricks[1].name, 32, "2-2/White");
    cat.bricks[1].width = 2; cat.bricks[1].height = 2;
    cat.bricks[1].color = (RGBValues){255,255,255};
    cat.bricks[1].stock = 10;
    cat.bricks[1].price = 1.0;

    Solution sol;

    // Test 1: Threshold 1 (Fixes the >= 0 split issue)
    // Variance in TL is 0. 0 >= 1 is False -> Keep as Leaf.
    Node* root = solve_quadtree(&img, &cat, 1, &sol, STOCK_STRICT, MATCH_COLOR);

    ASSERT_TRUE(root != NULL);
    // Root (4x4) has high variance, so it splits.
    ASSERT_EQ(root->is_leaf, 0);
    // Child 0 (Top Left) is perfect match, so it should be a leaf now.
    ASSERT_TRUE(root->child[0] != NULL);
    ASSERT_EQ(root->child[0]->is_leaf, 1);

    free_QUADTREE(root);
    solution_free(&sol);

    // Test 2: Huge Threshold (Should split only due to lack of 4x4 brick)
    root = solve_quadtree(&img, &cat, 999999, &sol, STOCK_STRICT, MATCH_COLOR);
    ASSERT_EQ(root->is_leaf, 0); // Must split because no 4x4 brick exists in catalog
    
    free_QUADTREE(root);
    solution_free(&sol);
    free(cat.bricks);
    free(img.pixels);
}

/* 6. Solution Memory Management */
static void test_solution_growth(void) {
    printf("[Running] Solution Memory...\n");
    Solution sol;
    solution_init(&sol, "stress_test");

    // Force realloc multiple times
    for (int i = 0; i < 500; i++) {
        int res = solution_push(&sol, "1-1/Test", i, i, 0);
        if (!res) {
            ASSERT_TRUE(0 && "Solution push failed (OOM?)");
        }
    }
    ASSERT_EQ(sol.count, 500);
    ASSERT_TRUE(sol.capacity >= 500);
    
    solution_free(&sol);
    ASSERT_TRUE(sol.placements == NULL);
    ASSERT_EQ(sol.count, 0);
}


/* =========================================================================
 * MAIN RUNNER
 * ========================================================================= */
int main(void) {
    printf("\n=== STARTING ROBUST TEST SUITE ===\n\n");

    test_utils_robustness();
    test_file_io_failures();
    test_stock_logic();
    test_tile_selected_logic();
    test_quadtree_recursion();
    test_solution_growth();

    printf("\n==================================\n");
    if (tests_failed == 0) {
        printf(ANSI_GREEN "ALL TESTS PASSED (%d/%d)\n" ANSI_RESET, tests_run, tests_run);
        return 0;
    } else {
        printf(ANSI_RED "TESTS FAILED: %d/%d\n" ANSI_RESET, tests_failed, tests_run);
        return 1;
    }
}