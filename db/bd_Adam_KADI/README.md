  Image2Brick Adam KADI

   1. Présentation du projet
Image2Brick est une application permettant de transformer une image en mosaïque de briques LEGO.
La base de données gère les utilisateurs, les images, les mosaïques générées, les commandes, le stock et la facturation.

   2. Modélisation des données
La modélisation repose sur un MCD.
Il décrit les entités principales (Utilisateur, Image, Commande, Pièce LEGO, etc.) ainsi que leurs relations et cardinalités.


   3. Règles métier implémentées
Certaines règles métier sont gérées directement par la base de données :
- impossibilité de modifier une commande validée
- création automatique d’une facture lors de la validation d’une commande
- déstockage automatique des pièces commandées
- recalcul automatique du montant total d’une commande

Ces règles sont implémentées à l’aide de triggers et de procédures stockées PostgreSQL.

   4. Contenu du rendu
- schema_img2brick.sql : script de création de la base de données
- MCD_img2brick.pdf : modèle conceptuel de données
- README.md : documentation du schéma

(Désolé encore de la gène occasionnée et merci pour votre tolérance)
