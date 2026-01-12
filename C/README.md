# Explications de c_final.c

Ce document décrit le rôle de chaque partie du code.

## Constantes et macros

- `MIN` / `MAX` : macros utilitaires.
- `NULL_PIXEL` : pixel marqueur pour les zones hors image (r,g,b = -1).
- `MAX_REGION_SIZE` : taille maximale d'une région de quadtree (16).
- `MAX_HOLE_NUMBER` : taille maximale du tableau `holes` dans les briques.
- `PRICE_QUALITY_TOL_PCT` : tolérance pour le biais prix (qualité dégradée max en %).

## Structures de données

- `RGBValues` : couleur RGB entière.
- `Brick` : une pièce du catalogue (dimensions, trous, couleur, prix, stock, nom).
- `Image` : image chargée + canvas rempli pour atteindre une puissance de 2.
- `Catalog` : tableau de `Brick` + taille.
- `BestMatch` : index du meilleur brick + diff de couleur.
- `RegionData` : moyenne de couleur, variance et nombre de pixels valides d'une région.
- `Node` : nœud de quadtree (région + moyenne + enfants).
- `Placement` : placement d'une brique (nom, x, y, rotation).
- `Solution` : liste des placements + stats (prix, qualité, stock).

## Énums

- `STOCK_RELAX` / `STOCK_STRICT` : mode de stock (autoriser ou non le manque).
- `MATCH_COLOR` / `MATCH_PRICE_BIAS` : mode de sélection des briques (pure couleur vs prix).

## Fonctions utilitaires

- `biggest_pow_2` : calcule la puissance de 2 >= n (pour le padding).
- `hex_to_RGB` : analyse une chaîne hexadécimale "RRGGBB" ou "#RRGGBB" vers `RGBValues`.
- `parse_holes` : transforme une chaîne "0123" ou "-1" en tableau d'entiers.
- `color_dist2` : distance euclidienne au carré entre deux couleurs.
- `avg_and_var` : calcule moyenne et variance d'une région (ignore `NULL_PIXEL`).
- `do_we_split` : décide si un nœud quadtree doit être coupé :
  - si la région est trop grande, la variance trop forte, non disponible dans le catalogue (ou hors stock en mode strict), ou déborde de l'image.
- `make_new_node` : alloue et initialise un nœud.
- `show_canvas` : debug (affiche l'image en hexadécimal si `DEBUG`).
- `free_QUADTREE` : libère tout le quadtree.

## I/O et parsing

- `load_image` :
  - lit la largeur et la hauteur, puis les pixels en hexadécimal.
  - crée un canvas carré de taille 2^n, rempli de `NULL_PIXEL`.
  - copie l'image dans le coin haut-gauche du canvas.
- `load_catalog` :
  - lit le nombre de lignes et les lignes du catalogue.
  - analyse les dimensions, les trous, la couleur, le prix et le stock.
  - construit `name` au format `w-h/hex` attendu par la partie Java.

### Pavage tile_with_selected

- parcourt l'image de gauche à droite, de haut en bas.
- pour chaque pixel non encore couvert :
  - tente de placer une brique à partir des dimensions préférées (`pref_w`,`pref_h`) avec alternance déterministe (weave).
  - pour chaque orientation, réduit la longueur progressivement (de la dimension maximale jusqu'à 2) pour trouver une pièce qui rentre et satisfait la contrainte de variance.
  - si le placement réussit, met à jour la solution et marque les pixels comme couverts.
  - si aucun placement multi-cellule n'est possible, retombe en `1×1`.

### Pavage 1×1

- `solve_1x1` :
  - pour chaque pixel valide, trouve la meilleure brique 1×1.
  - met à jour le prix, la qualité et les placements.

### Quadtree

- `QUADTREE_RAW` :
  - calcule moyenne/variance d'une région.
  - décide si on coupe via `do_we_split`.
  - si on ne coupe pas, choisit une brique de même taille et l'ajoute à la solution.
  - si on coupe, crée un nœud et appelle récursivement les 4 quadrants.
- `solve_quadtree` :
  - fonction capsule qui initialise la solution et appelle `QUADTREE_RAW`.

## Points importants

- Le quadtree cesse de se subdiviser (devient feuille) si la région est entièrement nulle, si elle est trop petite (1×1), si sa variance est faible, si une brique adéquate existe dans le catalogue (en tenant compte du mode stock) et si la région est dans les limites. À l'inverse, la logique force une subdivision lorsque la région dépasse `MAX_REGION_SIZE` (16×16) ou si la brique demandée n'existe pas (ou est hors stock en mode strict).
- Le padding au carré 2^n facilite les découpages par 2, mais peut ajouter des zones "nulles".
- Le mode `STOCK_RELAX` permet de produire une solution même si le stock est insuffisant (on compte les ruptures).

### Strict vs relax (stock)

- `STOCK_STRICT` : refuse une pièce si son stock est à 0 ou négatif, donc la solution peut échouer localement.
- `STOCK_RELAX` : autorise d'avoir un stock négatif (ruptures), et compte le nombre de ruptures.
