# Explications de MergeFile.c

Ce document decrit le role de chaque partie du code.

## Constantes et macros

- `MIN` / `MAX`: macros utilitaires.
- `NULL_PIXEL`: pixel marqueur pour les zones hors image (r,g,b = -1).
- `MAX_REGION_SIZE`: taille maximale d une region quadtree (16).
- `MAX_HOLE_NUMBER`: taille max du tableau `holes` dans les briques.
- `PRICE_QUALITY_TOL_PCT`: tolerance pour le biais prix (qualite degradee max en %).

## Structures de donnees

- `RGBValues`: couleur RGB entiere.
- `Brick`: une piece du catalogue (dimensions, trous, couleur, prix, stock, nom).
- `Image`: image chargee + canvas padde a une puissance de 2.
- `Catalog`: tableau de `Brick` + taille.
- `BestMatch`: index du meilleur brick + diff de couleur.
- `RegionData`: moyenne couleur, variance et compte des pixels valides d une region.
- `Node`: noeud de quadtree (region + moyenne + enfants).
- `Placement`: placement d une brique (nom, x, y, rotation).
- `Solution`: liste des placements + stats (prix, qualite, stock).

## Enums

- `STOCK_RELAX` / `STOCK_STRICT`: mode stock (autoriser ou non le manque).
- `MATCH_COLOR` / `MATCH_PRICE_BIAS`: mode de selection des briques (pure couleur vs prix).

## Fonctions utilitaires

- `biggest_pow_2`: calcule la puissance de 2 >= n (pour le padding).
- `hex_to_RGB`: parse un hex "RRGGBB" ou "#RRGGBB" vers `RGBValues`.
- `parse_holes`: transforme une chaine "0123" ou "-1" en tableau d entiers.
- `color_dist2`: distance euclidienne au carre entre deux couleurs.
- `avg_and_var`: calcule moyenne et variance d une region (ignore `NULL_PIXEL`).
- `do_we_split`: decide si un noeud quadtree doit etre coupe:
  - si region trop grande, variance trop forte, non disponible dans le catalogue, ou deborde de l image.
- `make_new_node`: alloue et initialise un noeud.
- `show_canvas`: debug (affiche l image en hex si `DEBUG`).
- `free_QUADTREE`: libere tout le quadtree.

### Stock / solution

- `price_to_cents`: convertit float en centimes.
- `catalog_has_piece` / `catalog_has_piece_in_stock`: verifie si un format existe, et si stock > 0.
- `catalog_clone`: duplique un catalogue pour usage independant.
- `consume_stock`: decremente stock (strict ou relax). En relax, compte les depassements.
- `solution_init` / `solution_push` / `solution_free`: gestion d une `Solution`.
- `write_solution_file`: ecrit la solution dans un fichier CSV simple.
- `piece_error_region`: somme des erreurs de couleur sur une region.
- `write_stock_report`: genere un rapport des pieces manquantes (non utilise dans main).
- `build_path`: assemble `outdir` et nom de fichier.
- `print_solution_summary_stdout`: imprime le resume d une solution.

## I/O et parsing

- `load_image`:
  - lit la largeur/hauteur, puis les pixels en hex.
  - cree un canvas carre de taille 2^n, rempli de `NULL_PIXEL`.
  - copie l image dans le coin haut-gauche du canvas.
- `load_catalog`:
  - lit le nombre de lignes et les lignes du catalogue.
  - parse dimensions, trous, couleur, prix, stock.
  - construit `name` au format `w-h/hex` attendu par la partie Java.

## Algorithmes de pavage

- `find_best_match_any`: meilleur match couleur pour une taille donnee (ignore stock).
- `find_best_match_in_stock`: meme chose mais stock > 0.
- `find_best_match_price_bias`:
  - trouve le meilleur diff couleur.
  - accepte des briques avec diff <= (meilleur + tol%).
  - choisit la moins chere parmi ces candidates.

### Pavage 4x2 + 1x1

- `solve_4x2_mix`:
  - parcourt l image de gauche a droite, haut en bas.
  - tente d'abord une piece 4x2 ou 2x4 (rotation possible) si la zone est libre.
  - choisit la brique qui minimise l erreur de couleur sur toute la region.
  - si aucune 4x2/2x4 n est possible, retombe sur une 1x1.

### Pavage 1x1

- `solve_1x1`:
  - pour chaque pixel valide, trouve la meilleure brique 1x1.
  - met a jour prix, qualite et placements.

### Quadtree

- `QUADTREE_RAW`:
  - calcule moyenne/variance d une region.
  - decide si on coupe via `do_we_split`.
  - si on ne coupe pas, choisit une brique de meme taille et l ajoute a la solution.
  - si on coupe, cree un noeud et recurse sur 4 quadrants.
- `solve_quadtree`:
  - fonction capsule qui initialise la solution et appelle `QUADTREE_RAW`.

## Fonction main

1. Parse les arguments:
   - image, catalogue, seuils, dossier de sortie.
2. Charge l image et le catalogue.
3. Clone le catalogue 6 fois (chaque solution consomme son propre stock).
4. Calcule 6 solutions:
   - 1x1 strict / relax
   - quadtree strict (match couleur) / quadtree relax (biais prix)
   - 4x2+1x1 strict / relax
5. Ecrit les fichiers de solution + resume stdout.
6. Libere toute la memoire.

## Points importants

- Le quadtree s arrete si la region n existe pas dans le catalogue, si la variance est faible, ou si la region depasse 16x16.
- Le padding au carre 2^n facilite les decoupages par 2, mais peut ajouter des zones "nulles".
- Le mode `STOCK_RELAX` permet de produire une solution meme si stock insuffisant (on compte les ruptures).

### Thresholds quadtree

- `threshold_low` (quadtree strict): seuil de variance qui declenche un split.
- `threshold_high` (quadtree relax): meme logique, avec biais prix.
- Plus bas: plus de decoupage, plus de pieces, meilleure fidelite couleur.
- Plus haut: moins de decoupage, moins de pieces, rendu plus grossier.

### Strict vs relax (stock)

- `STOCK_STRICT`: refuse une piece si son stock est a 0 ou negatif, donc la solution peut echouer localement.
- `STOCK_RELAX`: autorise de descendre sous 0 (stock manquant), et compte le nombre de ruptures.
