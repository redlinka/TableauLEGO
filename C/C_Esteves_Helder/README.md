# Pavage Optimisé en C

Ce programme permet de générer un pavage d’une image à partir de pièces disponibles, en minimisant à la fois le prix total et l’erreur de couleur par rapport à l’image originale.

---

## Commande pour l'exécution

Il est nécessaire d'avoir deux fichier nommé briques.txt et image.txt avec les format détailé plus bas dans le document

```bash
./pavage
```

Le programme générera un fichier de pavage dans le répertoire `./data/` et affichera sur la sortie standard une ligne avec :

- le chemin du fichier exporté
- le prix total du pavage
- la qualité (somme des différences de couleur)
- le nombre de pièces en rupture de stock

**Exemple :**

```bash
data/out.txt 3930 0 0
```

---

## Format des fichiers d'entrée

Le programme utilise deux fichiers au format texte (`.txt`) :

### 1. Fichier de la matrice de couleurs (`image.txt`)

Contient la matrice de pixels de l’image au format **RGB hexadécimal** sur 6 caractères, séparés par des espaces et/ou des sauts de ligne.
Avec sur la première ligne la Width et la Height

**Exemple de contenu :**

```bash
12 6
FF0000 00FF00 0000FF
FFFFFF 000000 FFFF00
```

---

### 2. Fichier d’informations sur les pièces (`briques.txt`)

Chaque ligne correspond à une pièce et contient les informations suivantes, séparées par un espace :

```text
nombre_de_forme nombre_de_couleur nombre_de_birque
Liste de forme
Liste de couleur
index_forme/index_couleur prix stock
```

**Explications des champs :**

- `index_forme` : position de la forme dans la liste de forme
- `index_couleur` : position de la couleur dans la liste de couleur
- `prix` : prix de la pièce
- `stock` : nombre de pièces disponibles

**Exemple de contenu :**

```text
1 4 4
1-1
000000
FF0000
00FF00
0000FF
0/0 100 100
0/1 101 100
0/2 106 100
0/3 106 100
```

---

## Format des fichiers de sortie

Chaque pavage généré est écrit dans un fichier texte dans `./data/`.

```
Nb_de_pieces prix erreur_de_couleur nb_piece_rupture_de_stock
dim_piece/couleur_hex i j rota
```

**Exemple de sortie (`out.txt`) :**

```text
35 3930 0 0
2x2/000000 0 0 0
```

---

## Compilation

```bash
gcc pavage.c -o pavage
```

---

## ▶️ Exécution

```bash
./pavage
```

**Résultat affiché sur la sortie standard (exemple) :**

```bash
./data/out.txt 143.64 521106 3
```

**Détail :**

- `143.64` → prix total du pavage (en euros)
- `521106` → somme des différences de couleurs
- `3` → nombre de pièces manquantes dans le stock
