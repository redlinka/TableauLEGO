# Fiche technique MergeFile.c (detaillee)

Ce document decrit le fonctionnement du code C `MergeFile.c` (pavage LEGO).

## 1) Vue d'ensemble

Le programme :
- Charge une image (pixels RGB) et un catalogue de briques.
- Choisit un algorithme de pavage (1x1, 2x1, 2x2, quadtree, combo, any, any_nostock, cheap).
- Genere un fichier de sortie listant les pieces placees.
- Affiche des statistiques (prix total, erreur couleur, ruptures de stock).
- Ecrit un fichier de "commande" listant les pieces manquantes.

Le critere principal est la qualite de couleur (distance RGB), puis la taille si egalite.

## 2) Structures de donnees

- `RGBValues` : un pixel (r, g, b).
- `Brick` : une brique avec :
  - `name` : identifiant texte.
  - `width`, `height` : dimensions en plots.
  - `holes` : type de trous (si besoin).
  - `color` : couleur RGB.
  - `price` : prix unitaire.
  - `number` : stock restant.
- `Image` :
  - `pixels` : tableau de pixels.
  - `width`, `height` : taille originale.
  - `canvasDims` : taille du canvas carre (puissance de 2) pour quadtree.
- `Catalog` : tableau dynamique de `Brick`.
- `BestMatch` : resultat d'un match par couleur (index + diff).
- `BestPiece` : resultat d'un choix de piece (index, taille, rotation, erreur).
- `RegionData` : moyenne couleur + variance d'une zone.
- `Node` : noeud du quadtree (zone + 4 enfants).
- `AlgoStats` : statistiques d'execution (prix, erreur, ruptures).

## 3) Fonctions utilitaires (couleur / calculs)

- `biggest_pow_2(n)` : calcule la plus petite puissance de 2 >= n.
  Utilise pour creer un canvas carre (quadtree).

- `hex_to_RGB(hex)` : convertit un code hex (6 caracteres) en RGB.

- `compare_colors(p1, p2)` : distance de couleur =
  (r1-r2)^2 + (g1-g2)^2 + (b1-b2)^2.

- `block_error(img, x, y, w, h, color)` : somme des erreurs couleur
  d'un bloc w*h par rapport a `color`.

- `avg_and_var(img, x, y, w, h)` : calcule la moyenne RGB et la variance
  sur une zone w*h. Sert a decider si une zone est homogene.

## 4) Quadtree

- `do_we_split(...)` : decide si on decoupe la zone en 4 sous-zones.
  Critere : variance trop elevee, region trop grande, ou depassement des bornes.

- `make_new_node(...)` / `free_QUADTREE(...)` : creation/destruction d'un noeud.

- `QUADTREE_RAW(...)` : algorithme recursif principal.
  Etapes :
  1. Calcul moyenne/variance.
  2. Si piece exacte trouvee pour w*h, et region homogene, on place directement.
  3. Sinon, on decoupe en 4 sous-zones et on recurse.

- `algo_quadtree(...)` : appelle `QUADTREE_RAW` sur le canvas entier.

## 5) Chargement d'image

- `load_image_dim(path)` : format "dimensions" :
  - Premiere ligne : `width height`.
  - Ensuite : liste de pixels hex en ligne ou sur plusieurs lignes.

- `load_image_matrix(path)` : format matrice :
  - Chaque ligne contient des pixels hex, separes par espaces.

- `load_image_auto(path, fmt)` : detecte le format automatiquement.

Les pixels sont ranges dans un tableau carre (canvas) :
les pixels hors image sont remplis par `NULL_PIXEL`.

## 6) Chargement du catalogue

Quatre formats supports :
- `nach` : CSV simple (w,h,trous,couleur,prix,stock).
- `kadi` : format texte (nom, w, h, r, g, b, prix, stock).
- `helder` : sections (shapes, couleurs, briques).
- `matheo` : liste de couleurs, puis liste de pieces.

`detect_catalog_format()` tente de deviner le format.
`load_catalog_auto()` charge avec le bon parseur.

## 7) Matching et stocks

- `find_best_match(color, w, h, catalog)` :
  choisit la brique en stock avec la meilleure couleur et la bonne taille.

- `find_best_match_rot(...)` : meme logique, mais essaie w/h et h/w.

- `update_stock(catalog, idx, stats)` : decremente le stock.
  Si stock negatif : incremente `ruptures`.

- `make_order_file(...)` : liste les briques en rupture (stock negatif).

## 8) Algorithmes de pavage (detail)

### 8.1 `algo_1x1`
- Parcourt l'image pixel par pixel.
- Pour chaque pixel, choisit la meilleure brique 1x1 en stock.
- Ecrit chaque piece et met a jour les stats.

### 8.2 `algo_match2x1`
- Parcourt l'image et tente de regrouper deux pixels adjacents :
  - Horizontal (2x1)
  - Vertical (1x2)
- Choisit la meilleure couleur moyenne.
- Marque les deux pixels comme places.
- Si aucun match possible, fallback 1x1.

### 8.3 `algo_blocks2x2`
- Identifie des blocs 2x2 homogenes (variance faible).
- Essaie de fusionner deux blocs :
  - en 4x2 horizontal
  - en 2x4 vertical
- Si pas de fusion, place un 2x2.
- Si toujours rien, fallback 1x1.

### 8.4 `algo_combo`
- Combine plusieurs passes :
  1) `algo_blocks2x2_partial` : place d'abord les 2x2/4x2/2x4.
  2) `algo_match2x1_partial` : place ensuite les 2x1/1x2.
  3) `algo_combo_prefer_large_partial` : tente de placer des pieces plus grandes
     en stock (2x2 ou 2x1) en cas d'espace libre.
  4) `algo_1x1_partial` : complete avec des 1x1.

### 8.5 `algo_any`
- Algorithme greedy qui essaie toutes les pieces en stock :
  - Pour chaque position libre, calcule l'erreur pour chaque brique
    (et rotation si possible).
  - Choisit celle avec l'erreur minimale.
  - En cas d'egalite, prefere la piece de plus grande surface.

### 8.6 `algo_any_nostock`
- Meme logique que `algo_any`, mais sans tenir compte du stock.
- Utile pour une sortie "sans contrainte de stock".

### 8.7 `algo_1x1_price_bias` (algo `cheap`)
- Variante 1x1 qui accepte un leger degrade de couleur pour reduire le prix.
- Pour chaque pixel, prend la brique la moins chere parmi celles dont l'erreur
  est <= (meilleure_erreur + threshold).

## 9) Gestion des zones occupees

- `region_is_free(...)` : verifie si une region est libre dans le masque `placed`.
- `mark_region(...)` : marque une region comme occupee.

## 10) Interface en ligne de commande

Usage :
```
MergeFile.exe <image> <catalog> <algo> <output> [threshold] [--catalog=fmt] [--image=fmt]
```

- `algo` : `1x1`, `match2x1`, `blocks2x2`, `quadtree`, `combo`, `any`, `any_nostock`, `cheap`, `all`.
- `threshold` : seuil pour quadtree/blocks2x2, et marge d'erreur pour `cheap`.
- `--catalog=fmt` : force le format catalogue (`nach`, `kadi`, `helder`, `matheo`).
- `--image=fmt` : force le format image (`dim`, `matrix`).

`algo=all` genere plusieurs sorties en serie (1x1, match2x1, blocks2x2,
quadtree, combo, any, any_nostock, cheap).

## 11) Fichiers generes

- Fichier de pavage : liste des pieces placees, format :
  `name x y rotation`.
- Fichier de commande : `order_*.txt` avec les pieces manquantes.
  
Sortie standard (exactement une ligne par pavage) :
`<output> <prix> <erreur> <ruptures>`

## 12) Points importants

## 13) Resume rapide par partie (utile et concis)

### A) Chargement
- Image: `load_image_dim` et `load_image_matrix` acceptent des pixels hex (RRGGBB).
  `load_image_auto` detecte le format et construit un canvas carre pour le quadtree.
- Catalogue: `load_catalog_*` parse les formats Nach/Kadi/Helder/Matheo.
  `load_catalog_auto` detecte automatiquement.

### B) Couleur et qualite
- `compare_colors` calcule la distance RGB au carre.
- `block_error` somme l'erreur sur un bloc; `block_error_avg` normalise par pixel.
- Les stats imprimees sont la somme d'erreurs (qualite) a l'echelle de l'image.

### C) Selection des briques
- `find_best_match` choisit la meilleure couleur a taille donnee (stock > 0).
- `find_best_match_rot` essaye la rotation (swap w/h).
- `find_best_match_price_bias` choisit la brique la moins chere dans une marge
  d'erreur (utilise par `cheap`).

### D) Algorithmes
- `1x1`: un pixel = une brique 1x1 de couleur la plus proche.
- `match2x1`: essaie 2x1/1x2 sur voisins puis fallback 1x1.
- `blocks2x2`: detecte 2x2 homogenes, tente 4x2/2x4, puis 2x2, puis 1x1.
- `quadtree`: decoupe recursivement selon la variance et place des blocs.
- `combo`: enchaine 2x2/4x2/2x4, puis 2x1/1x2, puis 1x1.
- `any`: choisit la meilleure piece (toute taille) par erreur moyenne.
- `any_nostock`: meme logique que `any`, sans decrementation du stock.
- `cheap`: 1x1 avec biais prix dans une marge d'erreur.

### E) Stock et sorties
- `update_stock` decremente et compte les ruptures.
- `emit_piece` ecrit `name x y rotation` dans le fichier de pavage.
- `make_order_file` liste les pieces manquantes dans `order_*.txt`.
- Stdout: `output prix erreur ruptures` (une ligne par execution).

### F) CLI
- `main` parse les arguments, charge image + catalogue, execute l'algo, imprime
  les stats, et libere la memoire.

- Les choix sont bases sur la couleur ; le prix est seulement une statistique.
- Le stock est respecte dans la selection (pas de choix si stock <= 0).
- La gestion des rotations est limitee a 90 degres (swap w/h).
