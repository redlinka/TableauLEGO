\# Pavage LEGO – Version 3 (COMPLET)



Cette version combine :

✔ V1 -> pièces 1x1 (couleur la plus proche, distance RGB²)

✔ V2 -> matching 2x1 (chemins alternants)

✔ V3 -> gestion des stocks + blocs 2x2 + remplacements



\## Format des fichiers

📌 pieces.txt :

&nbsp;   ID  W  H  R  G  B  PRIX  STOCK

📌 image.txt :

&nbsp;   LARGEUR  HAUTEUR  puis pixels en hex (RRGGBB)



\## Compilation

&nbsp;   make



\## Exécution

&nbsp;   ./pavage\_v3 data/pieces.txt data/image.txt out/pavage.out



\## Sortie standard attendue :

&nbsp;   chemin\_sortie prix\_total qualité\_totale ruptures\_stock



