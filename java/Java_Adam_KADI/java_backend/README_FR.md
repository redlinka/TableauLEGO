SAE Lego partie Java

Ce dossier contient la partie Java du backend pour la SAE Lego.

Il fournit :

* des outils pour redimensionner les images (plusieurs algorithmes) ;
* un client REST pour communiquer avec l'usine Lego (legofactory) ;
* une intégration avec le programme C pavage\_v3  via  ProcessBuilder .

Le code Java est structuré en packages :

* fr.uge.lego.image    : changement de résolution (nearest, bilinear, bicubic)
* fr.uge.lego.paving   : appel du programme C  pavage\_v3
* fr.uge.lego.brick    : description des types de briques (taille) et couleurs
* fr.uge.lego.stock    : gestion des entrées de stock (quantité + prix)
* fr.uge.lego.factory  : client API pour l'usine Lego
* fr.uge.lego.app      : petit exécutable de démonstration (CLI)

Compilation

1. Télécharger la bibliothèque   Gson   (par exemple  gson-2.11.0.jar )
   et la placer dans un dossier  lib/  :

   bash
   java\_backend/
   lib/gson-2.11.0.jar
   src/main/java/...

   

1. Compiler :

   bash
   cd java\_backend
   mkdir -p out
   javac -cp "lib/gson-2.11.0.jar" -d out $(find src/main/java -name "\*.java")

   

1. Exemple : tester le ping de l'usine :

   bash
   java -cp "out:lib/gson-2.11.0.jar" fr.uge.lego.app.LegoBackendMain ping-factory

   

   (En ayant défini  LEGOFACTORY\_EMAIL  et  LEGOFACTORY\_SECRET .)

   Intégration avec le C

   Le programme C  pavage\_v3  se lance ainsi :

   bash
   ./pavage\_v3 data/pieces.txt data/image.txt out/pavage.out

   

   La classe Java  CProgramPavingEngine  :

1. écrit deux fichiers temporaires ( pieces.txt  et  image.txt ) au   format attendu par le C   ;
2. lance  pavage\_v3  avec  ProcessBuilder  ;
3. lit la   ligne de résumé   sur  stdout  (chemin sortie, prix, qualité, ruptures) ;
4. renvoie un objet  PavingSolution  utilisable par le reste de l'application.

   Cette intégration respecte les consignes de la SAE (communication Java vers C par fichiers
   et ligne de résumé sur la sortie standard).

