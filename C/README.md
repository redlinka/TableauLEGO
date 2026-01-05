Documentation Projet TableauLEGO
Formats de Fichiers
Fichiers d'Entrée (Inputs)
image.txt
Ce fichier contient la matrice de pixels
Ligne 1 : <Largeur> <Hauteur> (en nombre de pixels)
Lignes suivantes : Les pixels sont fournis ligne par ligne, de gauche à droite, puis de haut en bas.

Exemple (Image 3x2) :

3 2
FF0000 00FF00 0000FF
FFFF00 FF00FF 00FFFF
catalog.txt
Ce fichier contient le catalogue des pièces en stock sous ce format : 
<nb_lignes> 
<w>,<h>,<holes>,<hex>,<price>,<stock>

<nb_lignes> : Le nombre de lignes totales du catalogue.
<w>,<h> : Les dimensions de la pièce.
<holes> : Le type de trous de la pièce (-1 standard sans trous).
<hex> : La couleur de la pièce.
<price> : Le prix de la pièce en euros (float).
<stock> : Le nombre en stock.

Exemple : 

40 → <nb_lignes>
1,1,-1,0020a0,0.01000,800000 → <w>,<h>,<holes>,<hex>,<price>,<stock>
1,1,-1,0020a0,0.01000,800000
…
Fichier de Sortie (Output)
Le programme génère des fichiers de pavage utilisant les différents algorithmes et détaillant les solutions trouvées.
solution algo.txt
Ce fichier contient la liste précise des pièces à placer pour reconstruire l'image avec les briques lego selon ce format : 
<shape> / <color> <x> <y>

<shape> : La forme de la pièce (ex: 1-1, 2-1, …)
<color> : Le code hexadécimal de la couleur de la pièce
<x> et <y> : Les coordonnées de la pièce dans le pavage

Exemple : 

1-1/3e3c39,3,0
1-1/4d4c52,448,0
1-1/4d4c52,449,0
1-1/737271,31,1
…
Sortie Standard (Console)
Après l'exécution de ces commandes : 

gcc -O2 -o merge MergeFile.c -lm
./merge <image_file> <catalog_file> [threshold_low] [threshold_high] [outdir]

La console affiche :

solution_1x1_strict.txt xxx xxx x
solution_1x1_relax.txt xxx xxx x
solution_quadtree_strict.txt xxx xxx x
solution_quadtree_relax.txt xxx xxx x
solution_4x2_strict.txt xxx xxx x
solution_4x2_relax.txt xxx xxx x

Chaque ligne correspond à ce format :

<filename> <price_cents> <quality> <stock_breaks>

<filename> :  Le nom du fichier output.
<price_cents> : Le prix en centimes.
<quality> : score de qualité (plus il est élevé, plus la correspondance couleur est bonne).
Calculé à partir de la distance couleur cumulée.
<stock_breaks> : Si le stock à été dépassé ou non.

Exemple : 

solution_1x1_strict.txt 799200 321541190 0
solution_1x1_relax.txt 799200 321541190 0
solution_quadtree_strict.txt 158636 381824691 0
solution_quadtree_relax.txt 44729 678537611 0
solution_4x2_strict.txt 102000 899177051 0
solution_4x2_relax.txt 102000 899177051 0


Explication des choix des algorithmes implémenté
Strict vs relax (stock)
STOCK_STRICT: refuse une pièce si son stock est a 0 ou négatif, donc la solution peut échouer localement.
STOCK_RELAX: autorise de descendre sous un stock de 0 (stock manquant) et compte le nombre de ruptures.
Il y a pour chaque algorithme une version stricte et une version relax.
Pavage 4x2 + 1x1
Explication : Prend en priorité des pièces 4x2 et ensuite place des 1x1 lorsque c’est impossible.
Avantage : Utilisation de moins de pièces. 
Inconvénient : Mosaique moins fidèle à l’image initiale.
Complexité : O(H⋅W⋅N) avec H = height et W = width et N = nombre total de briques.

Pavage 1x1
Explication : Place des pièces de type 1x1 disponibles dans le catalogue qui correspondent le plus possible à la couleur du pixel.
Avantages : Bonne qualité.
Inconvénients : Prix élevé.
Complexité : O(H⋅W⋅N) avec H = height et W = width et N = nombre total de briques.
Quadtree
Explications : La version quadtree strict privilégie le match couleur et la version quadtree relax accorde plus de biais pour optimiser le prix.
Avantages : Optimise le prix ou la qualité par rapport aux algos 1x1 ou 4x2 + 1x1. Grâce à plusieurs sous-divisions. Présente un meilleur compromis entre les 2 algos précédents.
Inconvénients : Peut détériorer un peu la qualité (relax) ou le prix (strict).
Complexité : O(D²⋅N) avec D = taille de la région.


