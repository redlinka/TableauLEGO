# SAE – Pavage de grille LEGO (V4)

Ce dossier contient une version **V4 conforme** (au sens du cahier des charges) du programme de pavage.

## Compilation

```bash
gcc -std=c11 -O2 -Wall -Wextra pavage_v4.c -lm -o pavage_v4
```

## Exécution

```bash
./pavage_v4 <image_file> <catalog_file> <threshold> [output_prefix]
```

- `threshold` : seuil utilisé par l'algorithme quadtree (plus il est petit, plus on découpe).
- `output_prefix` (optionnel) : préfixe des fichiers de sortie (défaut: `out`).

## Formats d'entrée

### Image
- 1ère ligne : `W H`
- puis `H` lignes de `W` couleurs au format **RRGGBB** séparées par des espaces.

Exemple :
```
3 2
FFFFFF 000000 FFFFFF
FF0000 00FF00 0000FF
```

### Catalogue
- 1ère ligne : nombre de lignes `N`
- puis `N` lignes au format :
`width, height, holes, color, price, stock`

Exemple :
```
6
1, 1, -1, ff0000, 0.10, 100
1, 4, 0832, ffff00, 0.35, 10
...
```

## Sorties (V4)

Le programme produit **4 pavages** différents et écrit **exactement 4 lignes** sur la sortie standard :

```
<chemin_sortie> <prix_total> <qualite_%> <rupture_stock>
```

- `rupture_stock` = nombre total de pièces manquantes (si on a dépassé le stock).
- `qualite_%` = 100 * (1 - somme_diff / diff_max), où diff est la distance RGB (comme dans le sujet).

### Fichiers de pavage
Chaque fichier de pavage contient **une ligne par pièce placée** au format CSV :

```
piece_name,x,y,rotation
```

(rotation vaut 0 dans cette version)

## Les 4 pavages générés

1. `*_quadtree_stock.txt` : quadtree **avec contrainte de stock**, objectif qualité.
2. `*_quadtree_nostock.txt` : quadtree **sans contrainte de stock**, objectif qualité.
3. `*_1x1_price.txt` : pavage **1x1** objectif **prix** (peut dégrader légèrement la qualité couleur).
4. `*_1x1_stock_quality.txt` : pavage **1x1** avec stock, objectif qualité.

Deux fichiers d'« ordre » (pièces manquantes) sont également générés :
- `order_quadtree_nostock.txt`
- `order_1x1_price.txt`
