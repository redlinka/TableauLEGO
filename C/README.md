# MergeFile.c

Ce programme transforme une image en "mosaic" de briques (type LEGO) en choisissant des pieces depuis un catalogue, en utilisant deux strategies:
- un pavage 1x1 (pixel par pixel)
- un pavage base sur un quadtree (regions adapteees a la variance)

Il produit 4 solutions:
- 1x1 strict (stock respecte)
- 1x1 relax (stock autorise a passer en negatif)
- quadtree strict (stock respecte, match couleur)
- quadtree relax (biais prix, tolere une petite perte de qualite)
- 4x2+1x1 strict (prefere 4x2/2x4, stock respecte)
- 4x2+1x1 relax (prefere 4x2/2x4, stock autorise a passer en negatif)

## Compilation

```bash
gcc -O2 -o merge MergeFile.c -lm
```

## Utilisation

```bash
./merge <image_file> <catalog_file> [threshold_low] [threshold_high] [outdir]
```

- `image_file`: fichier texte image
- `catalog_file`: fichier texte catalogue
- `threshold_low`: seuil variance pour quadtree strict (defaut 500)
- `threshold_high`: seuil variance pour quadtree relax (defaut 2000)
- `outdir`: dossier de sortie (defaut `.`)

## Format des fichiers

### Image

```
<largeur> <hauteur>
<pixel1> <pixel2> ... <pixelN>
```

- Chaque pixel est un hex RGB sur 6 caracteres, avec ou sans `#` (ex: `FF00AA` ou `#FF00AA`).
- Le nombre de pixels doit etre `largeur * hauteur`.

### Catalogue

```
<nb_lignes>
<w>,<h>,<holes>,<hex>,<price>,<stock>
...
```

- `holes` est une chaine de 0 a 4 chiffres (ex: `0123`) ou `-1`
- `hex` est un RGB hex (ex: `FF00AA` ou `#FF00AA`)
- `price` est un flottant (ex: `0.12`)
- `stock` est un entier (peut etre negatif si stock deja manquant)

## Sorties

Le programme ecrit 6 fichiers dans `outdir`:
- `solution_1x1_strict.txt`
- `solution_1x1_relax.txt`
- `solution_quadtree_strict.txt`
- `solution_quadtree_relax.txt`
- `solution_4x2_strict.txt`
- `solution_4x2_relax.txt`

Chaque ligne: `brick_name,x,y`

Le programme ecrit aussi un resume sur stdout:

```
<filename> <price_cents> <quality> <stock_breaks>
```

## Notes

- Le canvas interne est padde au plus grand carre 2^n, ce qui simplifie le quadtree.
- Pour de meilleurs resultats, utilisez une image dont largeur et hauteur sont des multiples de 16.
