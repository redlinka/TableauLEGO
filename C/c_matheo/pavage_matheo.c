#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <limits.h>

typedef struct color{
    char* rgb;
    struct color* col;
} Color;

typedef struct piece{
    char* format;
    char* trou;
    int prix;
    int stock;
} Piece;

typedef struct allpiece{
    Piece piece;     
    struct allpiece* col;
} Allpiece;

typedef struct pavage{
    Piece piece; 
    int dir;
    int pot;
    struct pavage* suivant;
} Pavage;

typedef struct pixel{
    char* color;
    struct pixel* pix;
} Pixel;

typedef Pixel* Mpixel;

typedef struct image {
    Mpixel imgae;
    int hauteur;
    int largeur;
} *Image;

Color* cons_C(Color* queue, char* elem){
    Color* l = malloc(sizeof(Color));
    l->rgb = elem;
    l->col = queue;
    return l;
}

typedef struct stock_actuelle{
    Allpiece* piece;
    Color* color;
} Stock_actuelle;

Allpiece* cons_P(Allpiece* queue, char* elem, char* elem2, int prix, int stock){
    Allpiece* l = malloc(sizeof(Allpiece));
    l->piece.format = elem;
    l->piece.trou = elem2;
    l->piece.prix = prix;
    l->piece.stock = stock;
    l->col = queue;
    return l;
}

Mpixel cons(Mpixel queue, char* elem){
    Mpixel l = malloc(sizeof(Pixel));
    l->color = elem;
    l->pix = queue;
    return l;
}

Pavage* cons_pav(Pavage* liste, Piece p, int dir, int pot) {
    Pavage* n = malloc(sizeof(Pavage));
    n->piece = p;       
    n->dir = dir;
    n->pot = pot;
    n->suivant = liste;
    return n;
}

Pavage* supprimer_pavage(Pavage* liste, Piece* cible) {
    if (liste == NULL) return NULL;
    if (&(liste->piece) == cible) {
        Pavage* tmp = liste->suivant;
        free(liste);
        return tmp;
    }
    Pavage* actuel = liste;
    while (actuel->suivant != NULL) {
        if (&(actuel->suivant->piece) == cible) {
            Pavage* a_supprimer = actuel->suivant;
            actuel->suivant = a_supprimer->suivant;
            free(a_supprimer);
            return liste;
        }
        actuel = actuel->suivant;
    }
    return liste;
}

int* char_int_piece(char* format, int dir){
    int *liste = malloc(2 * sizeof(int));
    if (dir == 1){
        liste[0] = format[0] - '0';
        liste[1] = format[2] - '0';
        return liste;
    }
    liste[1] = format[0] - '0';
    liste[0] = format[2] - '0';
    return liste;
}
//transforme mles code hexadecimal en decimal pour le calcul avec le rgb
int* hex_dec(char* color){
    int *liste = malloc(3 * sizeof(int));
    char r_hex[3]; 
    r_hex[0] = color[0];
    r_hex[1] = color[1];
    r_hex[2] = '\0';
    int r1 = strtol(r_hex, NULL, 16);
    liste[0] = r1;

    char g_hex[3]; 
    g_hex[0] = color[2];
    g_hex[1] = color[3];
    g_hex[2] = '\0';
    int g1 = strtol(g_hex, NULL, 16);
    liste[1] = g1;

    char b_hex[3]; 
    b_hex[0] = color[4];
    b_hex[1] = color[5];
    b_hex[2] = '\0';
    int b1 = strtol(b_hex, NULL, 16);
    liste[2] = b1;

    
    return liste;
}

int comparaison(int* rgb1,int* rgb2){

    return ((rgb1[0]-rgb2[0])*(rgb1[0]-rgb2[0]))+((rgb1[1]-rgb2[1])*(rgb1[1]-rgb2[1]))+((rgb1[2]-rgb2[2])*(rgb1[2]-rgb2[2]));

}

Stock_actuelle* piece_enr(){
    FILE * piece;
    piece = fopen("piece.txt","r");
    Color* all_color = NULL;
    char c;
    char* color = malloc(7 * sizeof(char));
    int indice = 0;
    while ((c = fgetc(piece)) != '/' && c != EOF){
        if(c != '\n' && c != ' '){
            color[indice] = c;
            indice++;
            
        }
        if(c == '\n'){
            color[6] = '\0';
            char* copie = malloc(7 * sizeof(char));
            strcpy(copie, color);
            all_color = cons_C(all_color, copie);
            indice = 0;
        }
        

    }
    color[6] = '\0';
    char* copie = malloc(7 * sizeof(char));
    strcpy(copie, color);
    all_color = cons_C(all_color, copie);
    free(color);

    Allpiece* all_piece = NULL;
    char format[16], trou[64];
    int prix, stock;
    while (fscanf(piece, "%15s %63s %d %d", format, trou, &prix, &stock) == 4) {
        all_piece = cons_P(all_piece, strdup(format), strdup(trou), prix, stock);
    }
    Stock_actuelle* s = malloc(sizeof(Stock_actuelle));
    s->color = all_color;
    s->piece = all_piece;
    return s;
}



Mpixel mirror_bis(Mpixel A, Mpixel B) { 
    if (B == NULL){
        return A;
    }
    char* copie = malloc(7);
    strcpy(copie, B->color);
    A = cons(A, copie);
    return mirror_bis(A,B->pix);

}
Mpixel mirror(Mpixel l) {
    Mpixel A = NULL;
    return mirror_bis(A, l);
}

Image img_to_matrice(FILE * image){
    Pixel* image_matrice = NULL;
    char c;
    char* color = malloc(7 * sizeof(char));
    int hauteur = 1;
    int largeur = 1;
    int indice = 0;
    while ((c = fgetc(image)) != EOF){
        if(c != '\n' && c != ' '){
            color[indice] = c;
            indice++;
            
        }
        if(c == '\n' || c == ' '){
            color[6] = '\0';
            char* copie = malloc(7 * sizeof(char));
            strcpy(copie, color);
            image_matrice = cons(image_matrice, copie);
            indice = 0;
            if (c == '\n'){
                hauteur++;
                largeur = 1;
            }
            if (c == ' ')largeur++;
        }

    }
    color[6] = '\0';
    char* copie = malloc(7 * sizeof(char));
    strcpy(copie, color);
    image_matrice = cons(image_matrice, copie);
    free(color);

    Image l = malloc(sizeof(struct image));
    l->imgae = mirror(image_matrice);
    l->hauteur = hauteur;
    l->largeur = largeur;
    return l;


}

void ecrie_liste(Mpixel l) {
    if (l == NULL) return;
    printf("%s ", l->color);
    ecrie_liste(l->pix);
}

void ecrie(Image image){
    printf("hauteur:%d    largeur:%d \n", image->hauteur, image->largeur);
    ecrie_liste(image->imgae);
    printf("\n");
}

int find_direction(Image image, int elem, int dir){
    int rep;
    switch (dir)
    {
    case 0:
        rep = elem - image->largeur ;
        if (rep >= 0) return rep;
        break;
    case 1:
        if (elem % image->largeur != 0) return rep + 1;
        break;
    case 2:
        rep = elem + image->largeur ;
        if (rep < image->largeur * image->hauteur) return rep;
        break;
    case 3:
        rep = elem - 1 ;
        if (rep != elem % image->largeur) return rep;
        break;
    }
    return -1;
}

char* comp_color(Mpixel l, Color* all_color){
    if (l == NULL || all_color == NULL) return NULL;

    int* color = hex_dec(l->color);

    Color* best = NULL;
    int best_score = INT_MAX;

    for (Color* c = all_color; c != NULL; c = c->col) {
        int* rgb_c = hex_dec(c->rgb);
        int score = comparaison(color, rgb_c);
        free(rgb_c); 
        if (score < best_score) {
            best_score = score;
            best = c;
        }
    }

    free(color);
    return best ? best->rgb : NULL;
}

Mpixel cc2(Mpixel p, Mpixel p2, Stock_actuelle* s){   
    if (p2 == NULL) return p; 
    return cc2(cons(p, comp_color(p2, s->color)), p2->pix, s); 
}

Image convertisseur_couleur(Image i, Stock_actuelle* s){
    Mpixel image_matrice = NULL;
    Mpixel m = cc2(image_matrice, i->imgae, s);  
    Image l = malloc(sizeof(struct image));
    l->imgae = mirror(m);
    l->hauteur = i->hauteur;
    l->largeur = i->largeur;
    return l;
}

Pavage* est_dans_piece(Image img, int p, Pavage* pav) {
    if (pav == NULL) return NULL;
    int* img1 = char_int_piece(pav->piece.format, pav->dir);
    int largeur_piece = img1[0];
    int hauteur_piece = img1[1];
    int index = 0;
    int value;
    for (int y = 0; y < hauteur_piece; y++) {
        for (int x = 0; x < largeur_piece; x++) {
            value = pav->pot + y * img->largeur + x;
            if (value == p && pav->piece.trou[index] == '0') {
                free(img1);
                return pav;
            }
            index++;
        }
    }
    free(img1);
    return est_dans_piece(img, p, pav->suivant);
}

Mpixel find(Mpixel img, int p){
    if (img == NULL) return NULL;
    if (p == 0) return img;
    return find(img->pix, p-1);
}


Piece* piece_existe(char* format, char* trou, Allpiece* a){
    if (a == NULL) return NULL;
    if (strcmp(a->piece.format, format) == 0 && strcmp(a->piece.trou, trou) == 0) return &(a->piece);
    return piece_existe(format, trou, a->col);
}

Pavage* fusion_brut11(Image img, Pavage* pav, int pot, Stock_actuelle* s) {
    if (est_dans_piece(img, pot, pav) != NULL)return pav;
    Mpixel po = find(img->imgae, pot);
    for (int dir = 0; dir < 4; dir++) {
        int voisin = find_direction(img, pot, dir);
        if (voisin == -1) continue;
        Mpixel pv = find(img->imgae, voisin);
        if (strcmp(po->color, pv->color) == 0) {
            Piece* p_exist = piece_existe("1x2", "00", s->piece);
            if (p_exist == NULL) return pav; 
            pav = cons_pav(pav, *p_exist, dir, pot);
            return pav;
        }
    }
    return pav;
}

Pavage* fusionner_image(Image img, Stock_actuelle* s) {
    Pavage* pav = NULL;  
    int total = img->hauteur * img->largeur;

    for (int pot = 0; pot < total; pot++) {
        pav = fusion_brut11(img, pav, pot, s);
    }

    return pav;
}

Pavage* fusionner_pixels_toutes_pieces(Image img, Stock_actuelle* s, Pavage* pav) {
    int total = img->hauteur * img->largeur;
    for (int pot = 0; pot < total; pot++) {
        Mpixel p = find(img->imgae, pot);
        if (p == NULL)
            continue;
        for (Allpiece* ap = s->piece; ap != NULL; ap = ap->col) {
            for (int dir = 0; dir < 4; dir++) {
                int* dims = char_int_piece(ap->piece.format, dir);
                int largeur_piece = dims[0];
                int hauteur_piece = dims[1];
                int index = 0;
                int valide = 1;
                for (int y = 0; y < hauteur_piece && valide; y++) {
                    for (int x = 0; x < largeur_piece; x++) {
                        int pos = pot + y * img->largeur + x;
                        if (pos < 0 || pos >= total) {
                            valide = 0;
                            break;
                        }
                        Mpixel px = find(img->imgae, pos);
                        if (!px || strcmp(px->color, p->color) != 0) {
                            valide = 0;
                            break;
                        }
                        if (ap->piece.trou[index] == '1') {
                            index++;
                            continue;
                        }
                        if (est_dans_piece(img, pos, pav) != NULL) {
                            valide = 0;
                            break;
                        }
                        index++;
                    }
                }
                if (valide) {
                    for (int y = 0; y < hauteur_piece; y++) {
                        for (int x = 0; x < largeur_piece; x++) {
                            int pos = pot + y * img->largeur + x;
                            Pavage* ancien = est_dans_piece(img, pos, pav);
                            if (ancien)
                                pav = supprimer_pavage(pav, &(ancien->piece));
                        }
                    }
                    pav = cons_pav(pav, ap->piece, dir, pot);
                    free(dims);
                    goto suivant_pixel;
                }
                free(dims);
            }
        }
        suivant_pixel:;
    }
    return pav;
}
void ecrire_pieces_fichier(
    const char* nom_fichier,
    Image img,
    Pavage* pav,
    Stock_actuelle* stock
){
    FILE* f = fopen(nom_fichier, "w");
    if (!f) {
        printf("Erreur ouverture fichier\n");
        return;
    }
    int total_pixels = img->hauteur * img->largeur;
    int prix_total = 0;
    int nb_pieces = 0;
    int nb_hors_stock = 0;
    for (Pavage* p = pav; p != NULL; p = p->suivant) {
        nb_pieces++;
        prix_total += p->piece.prix;

        if (piece_existe(p->piece.format, p->piece.trou, stock->piece) == NULL)
            nb_hors_stock++;
    }
    for (int i = 0; i < total_pixels; i++) {
        if (est_dans_piece(img, i, pav) == NULL) {
            nb_pieces++;

            Piece* p1 = piece_existe("1x1", "0", stock->piece);
            if (p1) prix_total += p1->prix;
            else nb_hors_stock++;
        }
    }
    fprintf(f, "%d %d %d\n", prix_total, nb_pieces, nb_hors_stock);
    for (Pavage* p = pav; p != NULL; p = p->suivant) {
        int x = p->pot % img->largeur;
        int y = p->pot / img->largeur;
        fprintf(f, "%s %s %s %d %d %d\n",
            p->piece.format,
            p->piece.trou,
            find(img->imgae, p->pot)->color,
            p->dir,
            x,
            y
        );
    }
    for (int i = 0; i < total_pixels; i++) {
        if (est_dans_piece(img, i, pav) == NULL) {
            Mpixel px = find(img->imgae, i);
            int x = i % img->largeur;
            int y = i / img->largeur;
            fprintf(f, "1x1 0 %s 0 %d %d\n",
                px->color,
                x,
                y
            );
        }
    }
    fclose(f);
}

int main(int argc, char* argv[]){
    FILE * test;
    FILE * test3;
    if (argc < 2) {
        printf("Usage : %s <chemin_fichier_image>\n", argv[0]);
        return 1;
    }
    test = fopen(argv[1], "r");
    test3 = fopen("resultat.txt", "w");   
    if (test == NULL){
        printf("erreur ouverture image\n");
        return 1;
    }
    Image matrice_de_limage = img_to_matrice(test);
    ecrie(matrice_de_limage);
    Stock_actuelle* all_stock = piece_enr();
    Image matrice_de_lego = convertisseur_couleur(matrice_de_limage, all_stock);
    ecrie(matrice_de_lego);
    Pavage* pav = NULL;
    pav = fusionner_pixels_toutes_pieces(matrice_de_lego, all_stock, pav);
    ecrire_pieces_fichier("resultat.txt", matrice_de_lego, pav, all_stock);
    printf("\nOK\n");
    fclose(test);
    fclose(test3);
    return 0;
}
