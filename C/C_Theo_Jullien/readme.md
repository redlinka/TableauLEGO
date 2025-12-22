# Fichiers d'entrée

---

### `pieces.txt`

Fichier contenant les pièces disponibles avec les différentes caractéristiques.
**Format du fichier :**

```
nom_couleur code_hexadecimal_couleur prix_en_centimes quantite_en_stock
```

**Exemple :**

```
red FF0000 20 1000
yellow FFFF00 15 2000
blue 0000FF 20 1500
```

---

### `image.txt`

Fichier contenant des codes hexadécimaux correspondant aux pixels de l'image.
**Format du fichier pour une image de 3x2 :**

```
code_hexa1 code_hexa2 code_hexa3
code_hexa4 code_hexa5 code_hexa6
```

**Exemple :**

```
FF0000 00FF00 0000FF
FFFF00 FF00FF 00FFFF
```

---

### `tiling.txt`

Indiquer le fichier qui contiendra le pavage (les codes hexadécimaux correspondant aux couleurs des pièces LEGO du stock de `pieces.txt`).

---

# Fichier de sortie

### `tiling.txt`

Fichier contenant les codes hexadécimaux correspondant aux couleurs des pièces LEGO du stock.
**Format du fichier pour une image de 1x2 :**

```
code_hexa1
code_hexa2
```

**Exemple :**

```
ff0000
800080
```

---

# Sortie standard

Affiche :

```
Output: chemin/vers/lefichier/contenant_le_pavage | Total Price: prix total de ce pavage | Quality: somme des erreurs sur chaque pixel | Number of items not in stock
```

**Exemple :**

```
Output: ./tilings/tiling.txt | Total Price: 60 | Quality: 97283 | Number of items not in stock: 0
```

---

# Instruction pour compiler le code

```
gcc tiling.c -o tiling
./tiling pieces.txt ./pictures/image.txt ./tilings/tiling.txt
```

# Test

Pour tester vous pouvez utiliser image.txt du dossier pictures ou créer le votre, vous pouvez choisir dans quel fichier créer le pavage, et vous pouvez utilisez un autre fichiers contenant les pièces à condition de respecter le format.

---

# Note

J'utilise quand même les pièces les plus fidèles aux pixels malgré que son stock est de 0.
J'ai rajouté un code que j'ai commenté que j'ai juste à décommenter si je veux empêcher d'utiliser des pièces dont le stock est de 0.

---

# Amélioration possible de mon code

* meilleure gestion des erreurs
* numéroté la matrice de pixels (demandé dans la consigne)
* respecter le critère du prix total des pièces pour choisir le pavage le moins cher car actuellement je privilégie uniquement la fidélité de la pièce par rapport au pixel
* indiquer en sortie identifiant de la pièce, coordonnées et rotation (demandé dans la consigne)

