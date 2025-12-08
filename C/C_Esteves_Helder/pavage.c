#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <assert.h>
#include <limits.h>
#define DEBUG_LOAD 1
#define MAX_COLORS 275

/* Structures */
// RGB: stocker une couleur
typedef struct
{
    int R;
    int G;
    int B;
} RGB;

// Image:
//  - dimensions
//  - rgb =  tableau de W*H couleurs
//    coordonnées (x,y) dans l'image -> case y*W + x du tableau
typedef struct
{
    int W;
    int H;
    RGB *rgb;
} Image;

// Liste de brique disponibles
// - nShape, W, H, T : tableaux de formes possibles (1x1, 1x2, etc), T = trous
// - nCol, bCol : tableau de couleurs possibles (RGB)
// - nBrique, bCol, bShape, bStock : liste de briques)
//    (indice de la couleur, indice de la forme, quantité en stock)
typedef struct
{
    int nShape;  // nombre de forme
    int nCol;    // nombre de couleur
    int nBrique; // nombre de brique
    int *W;      // liste des width des briques
    int *H;      // liste des height des briques
    int *T;      // liste des trou des briques
    RGB *col;    // liste des couleurs
    int *bCol;   // index de la couleur de la brique i
    int *bShape; // index de la forme de la brique i
    int *bPrix;  // prix de la brique
    int *bStock; // stock disponible
} BriqueList;

typedef struct
{
    int length;
    int *iBrique; // liste des briques en rupture de stock
    int *stock;   // liste des quantites de briques hors stock
} BriqueStock;

// Une brique placée dans la solution (référence de brique, coordonnées, rotation)
typedef struct
{
    int iBrique; // index de la brique dans BriqueList
    int x;       // pos x dans l'image
    int y;       // pos y dans l'image
    int rot;     // sa rotation
} SolItem;

// Solution complète (liste de briques placées + statistiques)
typedef struct
{
    int length;
    int totalError;
    int cost;
    int stock;
    SolItem *array; // liste des briques
} Solution;

/*** Gestion des fichiers ***/
FILE *open_with_dir(char *dir, char *name, char *mode)
{
    char filename[256];
    snprintf(filename, sizeof(filename), "%s/%s", dir, name);
    printf("open file %s (%s)\n", filename, mode);
    FILE *fptr = fopen(filename, mode);
    assert(fptr != NULL);
    return fptr;
}

/*** Image & couleur ***/

RGB *get(Image *I, int x, int y)
{
    return &(I->rgb[y * I->W + x]);
}
void reset(RGB *col)
{
    col->R = 0;
    col->G = 0;
    col->B = 0;
}

int colError(RGB c1, RGB c2)
{
    return (c1.R - c2.R) * (c1.R - c2.R) + (c1.G - c2.G) * (c1.G - c2.G) + (c1.B - c2.B) * (c1.B - c2.B);
}

/*** Tableau Solution ***/
// récupere la coordonnée
int getIndex(int x, int y, Image *I)
{
    return x + y * I->W;
}

// initialisation
void init_sol(Solution *sol, Image *I)
{
    sol->length = 0;
    sol->totalError = 0;
    sol->cost = 0;
    sol->stock = 0;
    // on alloue suffisament pour le pire cas (= une brique par pixel)
    sol->array = malloc(I->W * I->H * sizeof(SolItem));
}
// ajout d'une brique au pavage
void push_sol(Solution *sol, int iBrique, int x, int y, int rot, Image *I, BriqueList *B)
{
    assert(sol->length < I->W * I->H);
    sol->array[sol->length].iBrique = iBrique;
    sol->array[sol->length].x = x;
    sol->array[sol->length].y = y;
    sol->array[sol->length].rot = rot;
    sol->length++;
    sol->cost += B->bPrix[iBrique];
}
// calcul des stocks
void fill_sol_stock(Solution *sol, BriqueList *B)
{
    int *used = calloc(B->nBrique, sizeof(int));
    for (int i = 0; i < sol->length; i++)
    {
        used[sol->array[i].iBrique]++;
    }
    for (int i = 0; i < B->nBrique; i++)
    {
        int diff = used[i] - B->bStock[i];
        if (diff > 0)
            sol->stock += diff;
    }
    free(used);
}
/** Trous **/

int charToMask(char c)
{
    assert(c >= '0' && c <= '9');
    return 1 << (c - '0');
}
int coordToMask(int dx, int dy, int W)
{
    return 1 << (dx + W * dy);
}

int trou_str_to_int(char *buffer)
{
    int T = 0;
    for (int ibuffer = 0; buffer[ibuffer]; ibuffer++)
    {
        T += charToMask(buffer[ibuffer]);
    }
    return T;
}
void trou_int_to_str(int T, char *buffer)
{
    int ibuffer = 0;
    char current = '0';
    while (T > 0)
    {
        assert(current <= '9'); // trous max = 3x3
        if (T % 2 == 1)
        {
            buffer[ibuffer] = current;
            ibuffer++;
            T--;
        }
        current += 1;
        T /= 2;
    }
    buffer[ibuffer] = 0;
}
// Teste si une brique (avec trous et rotation) couvre les coordonnées rotx et roty (avec coin haut gauche =0,0)
int BriqueCovers(BriqueList *B, int ishape, int rot, int rotx, int roty)
{
    int W = B->W[ishape];
    int H = B->H[ishape];
    int T = B->T[ishape];
    // on calcule les coordonnées par rapport à la forme de base de la pièce (hors rotation)
    int dx = rotx;
    int dy = roty;
    if (rot == 1)
    {
        dx = roty;
        dy = H - rotx - 1;
    }
    else if (rot == 2)
    {
        dx = W - rotx - 1;
        dy = H - roty - 1;
    }
    else if (rot == 3)
    {
        dx = W - roty - 1;
        dy = rotx;
    }
    // on vérifie les bornes (W, H)
    if (dx < 0 || dy < 0 || dx >= W || dy >= H)
        return 0;
    // on vérifie que dx,dy ne tombe pas dans un trou
    if (T != 0 && (T & coordToMask(dx, dy, W)))
        return 0;
    return 1;
}

/** Export de solution **/
void print_sol(Solution *sol, char *dir, char *name, BriqueList *B)
{
    printf("%s/%s %d %d %d\n", dir, name, sol->cost, sol->totalError, sol->stock);
    FILE *fptr = open_with_dir(dir, name, "w");
    fprintf(fptr, "%d %d %d %d\n", sol->length, sol->cost, sol->totalError, sol->stock);
    for (int i = 0; i < sol->length; i++)
    {
        int ibrique = sol->array[i].iBrique;
        int ishape = B->bShape[ibrique];
        int icol = B->bCol[ibrique];
        if (B->T[ishape] == 0)
        {
            fprintf(fptr, "%dx%d/%02x%02x%02x %d %d %d\n",
                    B->W[ishape], B->H[ishape], B->col[icol].R, B->col[icol].G, B->col[icol].B, sol->array[i].x, sol->array[i].y, sol->array[i].rot);
        }
        else
        {
            char buffer[20];

            trou_int_to_str(B->T[ishape], buffer);
            fprintf(fptr, "%dx%d-%s/%02x%02x%02x %d %d %d\n",
                    B->W[ishape], B->H[ishape], buffer, B->col[icol].R, B->col[icol].G, B->col[icol].B, sol->array[i].x, sol->array[i].y, sol->array[i].rot);
        }
    }
}
/*** Chargement ***/
// lecture du fichier image
void load_image(char *dir, Image *I)
{
    FILE *fptr = open_with_dir(dir, "image.txt", "r");
    fscanf(fptr, "%d %d", &I->W, &I->H);
    I->rgb = malloc(I->W * I->H * sizeof(RGB));
    for (int j = 0; j < I->H; j++)
    {
        for (int i = 0; i < I->W; i++)
        {
            RGB col;
            reset(&col);
            int count = fscanf(fptr, "%02x%02x%02x", &col.R, &col.G, &col.B);
            assert(count == 3); // otherwise: file incomplete
            *get(I, i, j) = col;
            if (DEBUG_LOAD)
                printf(" %02x%02x%02x", col.R, col.G, col.B);
        }
        if (DEBUG_LOAD)
            printf("\n");
    }
    fclose(fptr);
    if (DEBUG_LOAD)
        printf("Image loaded, %dx%d\n", I->W, I->H);
}
void load_brique(char *dir, BriqueList *B)
{
    FILE *fptr = open_with_dir(dir, "briques.txt", "r");
    fscanf(fptr, "%d %d %d", &B->nShape, &B->nCol, &B->nBrique);
    assert(B->nCol <= MAX_COLORS);

    B->W = malloc(B->nShape * sizeof(int));
    B->H = malloc(B->nShape * sizeof(int));
    B->T = malloc(B->nShape * sizeof(int));
    B->col = malloc(B->nCol * sizeof(RGB));
    B->bCol = malloc(B->nBrique * sizeof(int));
    B->bShape = malloc(B->nBrique * sizeof(int));
    B->bPrix = malloc(B->nBrique * sizeof(int));
    B->bStock = malloc(B->nBrique * sizeof(int));
    if (DEBUG_LOAD)
        printf("%d shapes, %d colors, %d bricks\nShapes: ", B->nShape, B->nCol, B->nBrique);
    char buffer[80];
    for (int i = 0; i < B->nShape; i++)
    {
        int count = fscanf(fptr, "%d-%d-%s", &B->W[i], &B->H[i], buffer);
        assert(count >= 2 && count <= 3);
        if (DEBUG_LOAD)
            printf("[%d]%d-%d ", i, B->W[i], B->H[i]);
        if (count == 3)
        {
            int T = 0;
            for (int ibuffer = 0; buffer[ibuffer]; ibuffer++)
            {
                T += charToMask(buffer[ibuffer]);
            }
            B->T[i] = T;
            if (DEBUG_LOAD)
                printf("(%s -> %d) ", buffer, T);
        }
        else
            B->T[i] = 0;
    }
    if (DEBUG_LOAD)
        printf("\nColors: ");
    for (int i = 0; i < B->nCol; i++)
    {
        RGB col;
        int count = fscanf(fptr, "%02x%02x%02x", &col.R, &col.G, &col.B);
        assert(count == 3);
        B->col[i] = col;
        if (DEBUG_LOAD)
            printf("[%d]%02x%02x%02x", i, col.R, col.G, col.B);
    }

    if (DEBUG_LOAD)
        printf("\nBriques: ");
    for (int i = 0; i < B->nBrique; i++)
    {
        int count = fscanf(fptr, "%d/%d %d %d", &B->bShape[i], &B->bCol[i], &B->bPrix[i], &B->bStock[i]);
        assert(count == 4);
        if (DEBUG_LOAD)
            printf("[%d]%d/%d %d€x%d ", i, B->bShape[i], B->bCol[i], B->bPrix[i], B->bStock[i]);
    }
    if (DEBUG_LOAD)
        printf("\nLoading complete\n");
}

void freeData(Image I, BriqueList B)
{
    free(I.rgb);
    free(B.W);
    free(B.H);
    free(B.T);
    free(B.col);
    free(B.bShape);
    free(B.bCol);
    free(B.bPrix);
    free(B.bStock);
}
void freeSolution(Solution S)
{
    free(S.array);
}

/*** Outil de recherche de brique ***/
// retourne l'indice de la forme W x H, -1 si non trouvée
int lookupShape(BriqueList *B, int W, int H)
{
    for (int i = 0; i < B->nShape; i++)
    {
        if (B->W[i] == W && B->H[i] == H)
            return i;
    }
    return -1;
}

/*** Outil de gestion du stock ***/
void init_stock(BriqueStock *bs, BriqueList *b)
{
    bs->length = b->nBrique;
    bs->iBrique = calloc(b->nBrique, sizeof(int));
    bs->stock = calloc(b->nBrique, sizeof(int));
}

void push_brique_stock(BriqueStock *bs, int iBrique)
{
    if (bs->stock[iBrique] == 0)
    {
        bs->iBrique[iBrique] = iBrique;
    }

    bs->stock[iBrique]++;
}

void print_stock(BriqueStock *BS, char *dir, char *name)
{
    FILE *fptr = open_with_dir(dir, name, "w");
    fprintf(fptr, "id_brique stock\n");
    for (int i = 0; i < BS->length; i++)
    {
        int ibrique = BS->iBrique[i];
        int stock = BS->stock[i];

        fprintf(fptr, "%d %d\n", ibrique, stock);
    }
}

void freeStock(BriqueStock *bs)
{
    free(bs->iBrique);
    free(bs->stock);
}

/*** algorithmes de pavage ***/

//--------------- TP -------------------

// Q4
#define UNMATCHED -1

// Q3
typedef struct
{
    int u1;
    int v;
} Matching;

// Q2
int neighborIndex(int u, int dir, Image *I)
{
    assert(u >= 0 && u < I->H * I->W);
    assert(dir >= 0 && dir <= 3);

    int x = u % I->W;
    int y = u / I->W;
    switch (dir)
    {
    case 0:
        if (x + 1 >= I->W)
            return -1;
        return getIndex(x + 1, y, I);
    case 1:
        if (y + 1 >= I->H)
            return -1;
        return getIndex(x, y + 1, I);
    case 2:
        if (x - 1 < 0)
            return -1;
        return getIndex(x - 1, y, I);
    case 3:
        if (y - 1 < 0)
            return -1;
        return getIndex(x, y - 1, I);
    }
}

// Q5
void init(Matching *M, Image *I)
{
    for (int y = 0; y < I->H * I->W; y++)
    {
        M[y].u1 = y;
        M[y].v = UNMATCHED;
    }
}

// Q6
int getMatch(int u, Matching *M, int totalCells)
{
    assert(u >= 0 && u < totalCells);
    return M[u].v;
}

RGB getPixel(Image *I, int index)
{
    assert(index >= 0 && index < (I->W * I->H));
    return I->rgb[index];
}

// Q7
void greedyInsert(Matching *M, int u, Image *I)
{
    assert(u >= 0 && u < I->H * I->W);

    RGB cu = getPixel(I, u);
    // parcours les 4 voisins
    for (int d = 0; d < 4; d++)
    {
        int v = neighborIndex(u, d, I);
        if (v == -1)
            continue;

        RGB cv = getPixel(I, v);

        if (cu.R == cv.R && cu.G == cv.G && cu.B == cv.B)
        {

            if (M[v].v == UNMATCHED)
            {
                M[u].v = v;
                M[v].v = u;
                return;
            }
        }
    }
}

// Q8
int liberer(Matching *M, int u, Image *I, int *vu, int *placed)
{
    int N = I->W * I->H;
    if (vu[u])
        return 0; // deja visite
    vu[u] = 1;

    RGB cu = getPixel(I, u);
    // parcours les 4 voisins
    for (int d = 0; d < 4; d++)
    {
        int v = neighborIndex(u, d, I);
        if (v == -1)
            continue;

        RGB cv = getPixel(I, v);

        if (cu.R == cv.R && cu.G == cv.G && cu.B == cv.B)
        {
            if (placed[v])
                continue; // ignore les cases deja recouvertes

            if (M[v].v == UNMATCHED)
            {
                M[u].v = v;
                M[v].v = u;
                return 0;
            }
            else
            {
                int w = M[v].v; // on recupere le voisin du voisin de u
                // on essaie de liberer w
                if (liberer(M, w, I, vu, placed))
                {
                    M[u].v = v;
                    M[v].v = u;
                    return 1;
                }
            }
        }
    }
    return 0;
}

void optimalInsert(Matching *M, int u, Image *I, int *placed)
{
    int N = I->W * I->H;
    if (M[u].v != UNMATCHED)
        return; // u est deja matche

    int vu[N];
    // initialise toutes les cases en non vue
    for (int i = 0; i < N; i++)
    {
        vu[i] = 0;
    }

    liberer(M, u, I, vu, placed);
}

//======================Fonction 2x2======================

void initMatching(Matching *M, int numBlocks)
{
    for (int i = 0; i < numBlocks; i++)
    {
        M[i].u1 = i;
        M[i].v = UNMATCHED;
    }
}

/*** Vérifie si le bloc 2x2 en (x,y) a toutes les cases de même couleur ***/
int is2x2SameColor(int x, int y, Image *I)
{
    if (x + 1 >= I->W || y + 1 >= I->H)
        return 0;

    RGB c1 = getPixel(I, getIndex(x, y, I));
    RGB c2 = getPixel(I, getIndex(x + 1, y, I));
    RGB c3 = getPixel(I, getIndex(x, y + 1, I));
    RGB c4 = getPixel(I, getIndex(x + 1, y + 1, I));

    return (c1.R == c2.R && c1.R == c3.R && c1.R == c4.R) &&
           (c1.G == c2.G && c1.G == c3.G && c1.G == c4.G) &&
           (c1.B == c2.B && c1.B == c3.B && c1.B == c4.B);
}

/*** Vérifie si deux blocs 2x2 ont la même couleur ***/
int same2x2Color(int x1, int y1, int x2, int y2, Image *I)
{
    RGB c1 = getPixel(I, getIndex(x1, y1, I));
    return is2x2SameColor(x1, y1, I) && is2x2SameColor(x2, y2, I) &&
           c1.R == getPixel(I, getIndex(x2, y2, I)).R &&
           c1.G == getPixel(I, getIndex(x2, y2, I)).G &&
           c1.B == getPixel(I, getIndex(x2, y2, I)).B;
}

/*** Matching glouton pour fusion 2x2 -> 4x2 ***/
void greedyInsert2x2(Matching *M, int i, int *blockX, int *blockY, int numBlocks, Image *I)
{
    assert(i >= 0 && i < I->H * I->W);
    // Si la case a deja un voisin on skip
    if (M[i].v != UNMATCHED)
    {
        return;
    }

    int dirs[4][2] = {
        {2, 0},  // droite
        {-2, 0}, // gauche
        {0, 2},  // bas
        {0, -2}  // haut
    };
    // On recupere les coordonnees du block i
    int x = blockX[i];
    int y = blockY[i];

    for (int d = 0; d < 4; d++)
    {
        int nx = x + dirs[d][0];
        int ny = y + dirs[d][1];

        // Verifier que le voisin est dans l’image
        if (nx < 0 || ny < 0 || nx >= I->W || ny >= I->H)
            continue;

        // On cherche le bloc j qui a ces coordonnees
        for (int j = 0; j < numBlocks; j++)
        {
            if (blockX[j] == nx && blockY[j] == ny)
            {
                if (M[j].v != UNMATCHED || j == i)
                {
                    continue;
                }

                //  Si le block j a la meme couleur que le block i on les combines
                if (same2x2Color(x, y, nx, ny, I))
                {
                    M[i].v = j;
                    M[j].v = i;
                    return;
                }
            }
        }
    }
}

/*** Création de tous les blocs 2x2 de l'image ***/
int create2x2Blocks(int *blockX, int *blockY, Image *I)
{
    int N = I->W * I->H;
    int *used = calloc(N, sizeof(int));
    int n = 0;

    for (int y = 0; y < I->H - 1; y++)
    {
        for (int x = 0; x < I->W - 1; x++)
        {
            // Si la case na pas la meme couleur que c voisin on skip
            if (!is2x2SameColor(x, y, I))
                continue;
            int idx1 = getIndex(x, y, I);
            int idx2 = getIndex(x + 1, y, I);
            int idx3 = getIndex(x, y + 1, I);
            int idx4 = getIndex(x + 1, y + 1, I);

            // si deja compte -> on skip
            if (used[idx1] || used[idx2] || used[idx3] || used[idx4])
            {
                continue;
            }

            // Ajouter le bloc
            blockX[n] = x;
            blockY[n] = y;
            n++;

            // Marquer les 4 cases comme utilisées
            used[idx1] = 1;
            used[idx2] = 1;
            used[idx3] = 1;
            used[idx4] = 1;
        }
    }

    free(used);
    return n;
}
//============================================

Matching *initMatchingAndPairs(Image *I, int *placed, BriqueList *B)
{
    int N = I->W * I->H;

    Matching *M = malloc(N * sizeof(Matching));
    init(M, I);

    // Pour chaque pièce on lui assigne un voisin
    for (int u = 0; u < N; u++)
    {
        // Si la piece a deja ete place on la skip
        if (placed[u])
            continue;
        if (M[u].v == UNMATCHED)
        {
            int shape21 = lookupShape(B, 2, 1); // récupère l’indice de la brique 2x1

            // Si la brique 2x1 est en stock on essaye de l'inserer
            if (B->bStock[shape21] != 0)
            {
                optimalInsert(M, u, I, placed);
            }
            else
            {
                printf("[%d]Pas de brique 2x1 en stock\n", u);
            }
        }
    }
    return M;
}

void mapBricksToColors(BriqueList *B,
                       int brique11WithColor[MAX_COLORS],
                       int brique21WithColor[MAX_COLORS],
                       int *shape11,
                       int *shape21)
{
    // Recupere l'indice des formes
    *shape11 = lookupShape(B, 1, 1);
    *shape21 = lookupShape(B, 2, 1);

    // initialise la couleur des briques à -1
    for (int i = 0; i < MAX_COLORS; i++)
    {
        brique11WithColor[i] = -1;
        brique21WithColor[i] = -1;
    }

    // Associe les couleurs aux briques displonibles correspondantes
    for (int i = 0; i < B->nBrique; i++)
    {
        if (B->bShape[i] == *shape21)
        {
            int indexColor = B->bCol[i];
            assert(brique21WithColor[indexColor] == -1);
            brique21WithColor[indexColor] = i;
        }
        else if (B->bShape[i] == *shape11)
        {
            int indexColor = B->bCol[i];
            assert(brique11WithColor[indexColor] == -1);
            brique11WithColor[indexColor] = i;
        }
    }
}

Solution run_algo_1(Image *I, BriqueList *B, int *placed)
{
    int W = I->W, H = I->H;
    int N = W * H;

    Solution S;
    init_sol(&S, I);

    // Matching horizontal + vertical pour 1×2
    Matching *M = initMatchingAndPairs(I, placed, B);

    // Briques 1x1 / 2x1
    int shape11, shape21;
    int brique11WithColor[MAX_COLORS];
    int brique21WithColor[MAX_COLORS];

    mapBricksToColors(B, brique11WithColor, brique21WithColor, &shape11, &shape21);

    int *closestColor = malloc(N * sizeof(int));
    int totalError = 0;

    for (int u = 0; u < N; u++)
    {
        int bestCol = -1;
        int bestColError = INT_MAX;
        RGB colorI = getPixel(I, u);

        if (M[u].v != UNMATCHED && M[u].v > u) // si y'a un match, on fait le 2x1
        {
            for (int c = 0; c < B->nCol; c++)
            {
                if (brique21WithColor[c] == -1)
                    continue; // pas de brique 2x1 pour cette couleur
                int err = colError(colorI, B->col[c]);
                if (err < bestColError && B->bStock[brique21WithColor[c]] > 0)
                {
                    bestColError = err;
                    bestCol = c;
                }
            }
        }

        if (M[u].v == UNMATCHED || bestCol == -1) // sinon on fait le 1x1
        {
            for (int col = 0; col < B->nCol; col++)
            {
                if (brique11WithColor[col] == -1)
                    continue;
                int err = colError(colorI, B->col[col]);
                if (err < bestColError && B->bStock[brique11WithColor[col]] > 0)
                {
                    bestColError = err;
                    bestCol = col;
                }
            }
        }

        closestColor[u] = bestCol;
        totalError += bestColError;
    }

    for (int u = 0; u < N; u++)
    {
        // Si la case est déjà placée, on skip
        if (placed && placed[u])
            continue;

        int color = closestColor[u];
        if (color == -1)
            continue; // fallback si aucune couleur calculée

        int x = u % W;
        int y = u / W;

        // Cas 2x1
        if (M[u].v != UNMATCHED && u < M[u].v)
        {
            int v = M[u].v;
            int rot = ((v / W) == y) ? 0 : 1;
            int ibrick2x1 = brique21WithColor[color];

            if (ibrick2x1 != -1 && B->bStock[ibrick2x1] > 0)
            {
                // Placer la brique 2x1

                push_sol(&S, ibrick2x1, x, y, rot, I, B);
                B->bStock[ibrick2x1]--;

                int w = (rot == 0) ? 2 : 1;
                int h = (rot == 0) ? 1 : 2;

                for (int dx = 0; dx < w; dx++)
                    for (int dy = 0; dy < h; dy++)
                        placed[getIndex(x + dx, y + dy, I)] = 1;
            }
            else
            {
                // Pas de brique 2x1 disponible, on place deux 1x1
                int ibrick1x1 = brique11WithColor[color];

                push_sol(&S, ibrick1x1, x, y, 0, I, B);
                push_sol(&S, ibrick1x1, v % W, v / W, 0, I, B);
                B->bStock[ibrick1x1] -= 2; // Decremente le stock

                placed[u] = placed[v] = 1;
            }
            continue;
        }

        // Cas 1x1 (ou fallback)
        int ibrick1x1 = brique11WithColor[color];
        if (ibrick1x1 != -1)
        {
            push_sol(&S, ibrick1x1, x, y, 0, I, B);
            B->bStock[ibrick1x1]--; // Decremente le stock
            placed[u] = 1;
        }
    }
    S.totalError = totalError;

    free(closestColor);
    free(M);

    return S;
}

Solution run_algo2x2(Image *I, BriqueList *B)
{
    Solution S;
    init_sol(&S, I);

    int placed[I->W * I->H];
    memset(placed, 0, sizeof(placed));

    int shape22 = lookupShape(B, 2, 2);
    int shape42 = lookupShape(B, 4, 2);
    int brique22WithColor[MAX_COLORS];
    int brique42WithColor[MAX_COLORS];
    for (int i = 0; i < MAX_COLORS; i++)
    {
        brique22WithColor[i] = -1;
        brique42WithColor[i] = -1;
    }

    for (int i = 0; i < B->nBrique; i++)
    {
        if (B->bShape[i] == shape22)
        {
            int col = B->bCol[i];
            brique22WithColor[col] = i;
        }
        else if (B->bShape[i] == shape42)
        {
            int col = B->bCol[i];
            brique42WithColor[col] = i;
        }
    }

    // Création de tous les blocs 2x2
    int maxBlocks = (I->W / 2) * (I->H / 2);
    // Position x et y d'un block
    int blockX[maxBlocks], blockY[maxBlocks];
    int numBlocks = create2x2Blocks(blockX, blockY, I); // Quantite de blocks 2x2 qu'il y aura dans le pavage
    // Initialisation du matching
    Matching *M = malloc(numBlocks * sizeof(Matching));
    initMatching(M, numBlocks);

    for (int i = 0; i < numBlocks; i++)
    {
        greedyInsert2x2(M, i, blockX, blockY, numBlocks, I);
    }

    int totalError = 0;

    // Parcours des blocs et placement
    for (int i = 0; i < numBlocks; i++)
    {
        int x = blockX[i];
        int y = blockY[i];

        int idx1 = getIndex(x, y, I);
        int idx2 = getIndex(x + 1, y, I);
        int idx3 = getIndex(x, y + 1, I);
        int idx4 = getIndex(x + 1, y + 1, I);

        // Si deja place via fusion, on ignore
        if (placed[idx1] || placed[idx2] || placed[idx3] || placed[idx4])
            continue;

        int bestCol = -1;
        int bestErr = INT_MAX;

        RGB c = getPixel(I, idx1);

        // Bloc fusionne
        if (M[i].v != UNMATCHED && i < M[i].v)
        {
            int j = M[i].v;
            int nx = blockX[j];
            int ny = blockY[j];

            // Placer un bloc 4x2
            for (int col = 0; col < B->nCol; col++)
            {
                if (brique42WithColor[col] == -1)
                    continue;
                int err1 = colError(c, B->col[col]);
                if (err1 < bestErr)
                {
                    if (B->bStock[brique42WithColor[col]] > 0)
                    {
                        bestErr = err1;
                        bestCol = col;
                    }
                }
            }

            if (bestCol != -1)
            {
                int rota = ny == y ? 0 : 1;
                B->bStock[brique42WithColor[bestCol]]--; // Decremente le stock
                int ibrick = brique42WithColor[bestCol];
                push_sol(&S, ibrick, x, y, rota, I, B);

                int w = (rota == 0) ? 4 : 2;
                int h = (rota == 0) ? 2 : 4;

                // Marquer les 8 cellules du bloc 4x2 ou 2x4 -> selon la rotation de la piece
                for (int dx = 0; dx < w; dx++)
                {
                    for (int dy = 0; dy < h; dy++)
                    {
                        placed[getIndex(x + dx, y + dy, I)] = 1;
                    }
                }

                totalError += bestErr;
            }
        }
        // Si la piece n'a pas de voisin ou sil y a pas eu de piece 4x2 disponible on place une piece 2x2
        if (M[i].v == UNMATCHED || bestCol == -1)
        {
            // Selection couleur pour une piece 2x2
            for (int col = 0; col < B->nCol; col++)
            {
                if (brique22WithColor[col] == -1)
                    continue;
                int err1 = colError(c, B->col[col]);
                if (err1 < bestErr)
                {
                    if (B->bStock[brique22WithColor[col]] > 0)
                    {
                        bestErr = err1;
                        bestCol = col;
                    }
                }
            }

            if (bestCol != -1)
            {
                int ibrick = brique22WithColor[bestCol];
                B->bStock[brique22WithColor[bestCol]]--; // Decremente le stock
                push_sol(&S, ibrick, x, y, 0, I, B);

                placed[idx1] = placed[idx2] = placed[idx3] = placed[idx4] = 1;
                totalError += bestErr;
            }
        }
    }

    free(M);

    S.totalError = totalError;
    return S;
}

/*Fonction de test qui affiche la matrice de couleur*/
void printColorMatrix(Solution *S, Image *I, BriqueList *B)
{
    int W = I->W;
    int H = I->H;

    // Crée une matrice couleur pour toute l'image
    RGB **matrix = malloc(H * sizeof(RGB *));
    for (int y = 0; y < H; y++)
    {
        matrix[y] = malloc(W * sizeof(RGB));
        for (int x = 0; x < W; x++)
        {
            matrix[y][x].R = matrix[y][x].G = matrix[y][x].B = 0; // init noir
        }
    }

    // Remplir la matrice avec les couleurs des briques
    for (int k = 0; k < S->length; k++)
    {
        int x0 = S->array[k].x;
        int y0 = S->array[k].y;
        int ibrick = S->array[k].iBrique;
        int rot = S->array[k].rot;

        int shape = B->bShape[ibrick];
        int w = B->W[shape];
        int h = B->H[shape];

        if (rot)
        { // rotation 90°
            int tmp = w;
            w = h;
            h = tmp;
        }

        RGB color = B->col[B->bCol[ibrick]];

        for (int dx = 0; dx < w; dx++)
        {
            for (int dy = 0; dy < h; dy++)
            {
                int x = x0 + dx;
                int y = y0 + dy;
                if (x < W && y < H)
                    matrix[y][x] = color;
            }
        }
    }

    // Afficher la matrice (R,G,B)
    for (int y = 0; y < H; y++)
    {
        for (int x = 0; x < W; x++)
        {
            printf("u:%d = (%3d,%3d,%3d) ", getIndex(x, y, I), matrix[y][x].R, matrix[y][x].G, matrix[y][x].B);
        }
        printf("\n");
    }

    // Libération
    for (int y = 0; y < H; y++)
        free(matrix[y]);
    free(matrix);
}

Solution run_algo_main(Image *I, BriqueList *B)
{

    //================ Pavage 2×2 + fusion 4×2 ================

    Solution S1 = run_algo2x2(I, B);

    // On recupere quelles cellules ont ete posees par run_algo2x2
    int W = I->W, H = I->H;
    int *placed = calloc(W * H, sizeof(int));

    for (int k = 0; k < S1.length; k++)
    {
        int x = S1.array[k].x;
        int y = S1.array[k].y;
        int rot = S1.array[k].rot;

        int ibrique = S1.array[k].iBrique;
        int ishape = B->bShape[ibrique];

        int w = (rot == 0 ? B->W[ishape] : B->H[ishape]);
        int h = (rot == 0 ? B->H[ishape] : B->W[ishape]);

        for (int dx = 0; dx < w; dx++)
        {
            for (int dy = 0; dy < h; dy++)
            {
                placed[getIndex(x + dx, y + dy, I)] = 1;
            }
        }
    }

    //================ Final MERGE ================

    Solution S2 = run_algo_1(I, B, placed);
    int totalError2 = S2.totalError;

    Solution S_final;
    init_sol(&S_final, I);

    // Ajouter toutes les pieces de S1
    for (int k = 0; k < S1.length; k++)
        push_sol(&S_final,
                 S1.array[k].iBrique,
                 S1.array[k].x,
                 S1.array[k].y,
                 S1.array[k].rot,
                 I, B);

    // Ajouter toutes les pieces de S2
    for (int k = 0; k < S2.length; k++)
        push_sol(&S_final,
                 S2.array[k].iBrique,
                 S2.array[k].x,
                 S2.array[k].y,
                 S2.array[k].rot,
                 I, B);

    S_final.totalError = S1.totalError + totalError2;

    free(placed);

    return S_final;
}

int main(int argn, char **argv)
{
    char *dir = "data";
    if (argn > 1)
        dir = argv[1];
    Image I;
    BriqueList B;
    BriqueStock bStock;
    init_stock(&bStock, &B);
    load_image(dir, &I);
    load_brique(dir, &B);

    printf("Main Algo\n");
    Solution S2 = run_algo_main(&I, &B);
    print_sol(&S2, dir, "out.txt", &B);
    freeSolution(S2);

    freeData(I, B);
    return 0;
}