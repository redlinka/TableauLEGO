# SAE Lego - Esteves Helder

## Sommaire

- Introduction
- Architecture du projet
- Choix d’implémentation
- Difficultés rencontrées
- Limitations et bugs connus
- Guide d’utilisation

## Introduction

SAE Lego est un projet permettant de transformer une image en pavage Lego.
Le programme Java prend une image et la redimensionne en appliquant différentes méthodes d'interpolation.

## Architecture du projet

img2brick/
│
├── src/
│   └── main/
│       └── java/
│           ├── fr/
│           │   └── uge/
│           │       └── lego/
│           │           ├── backend/
│           │           │   ├── domain/
│           │           │   │   ├── Brick.java
│           │           │   │   └── Order.java
│           │           │   │
│           │           │   ├── factory/
│           │           │   │   ├── FactoryService.java
│           │           │   │   ├── PaymentMethod.java
│           │           │   │   └── ProofOfWorkSolver.java
│           │           │   │
│           │           │   └── service/
│           │           │       └── StockManager.java
│           │           │
│           │           ├── image/
│           │           │   ├── BicubicResize.java
│           │           │   ├── BilinearResize.java
│           │           │   ├── MultiStepResizeStrategy.java
│           │           │   ├── NearestNeighborResize.java
│           │           │   └── ResizeStrategy.java
│           │           │
│           │           ├── main/
│           │           │   ├── ImageUtils.java
│           │           │   ├── Main.java
│           │           │   ├── doc.txt
│           │           │   └── README.md
│           │           │
│           │           └── matrix/
│           │               └── Matrix.java
│           │
│           └── ressources/
│               ├── bw.jpg
│               ├── kirby.png
│               ├── multiStepResult.jpg
│               └── spiderman.jpg
│
├── pom.xml
└── (autres fichiers Maven éventuels)


Le projet est organisé en plusieurs packages selon leurs utilités, respectant une architecture modulaire.
Le projet suit la structure standard Maven avec un dossier src qui contient les packages Java et un dossier ressources avec les images utilisées pour les tests.

*Domain (``fr.uge.lego.backend.domain``)*
- Brick.java : Classe représentant une brique Lego physique.
- Order.java : Représente une commande ou une demande de devis adressée à l’usine.

*Factory (``fr.uge.lego.backend.factory``)*
- FactoryService.java : Service de communication avec l’API REST de l’usine, gérant les devis, commandes et livraisons.
- PaymentMethod.java : Interface définissant le contrat pour les méthodes de paiement.
- ProofOfWorkSolver.java : Implémentation concrète de la méthode de paiement, basée sur un algorithme de preuve de travail.

*Service (``fr.uge.lego.backend.service``)*
- StockManager.java : Façade métier orchestrant la gestion du stock, vérification des disponibilités, gestion des commandes et réapprovisionnements.

*Image (``fr.uge.lego.image``)*
Ce package contient les différentes stratégies d’interpolation utilisées pour redimensionner les images :
- NearestNeighborResize.java : Implémente l’interpolation au plus proche voisin, rapide mais avec une qualité moindre (crénelage).
- BilinearResize.java : Implémente l’interpolation bilinéaire, utilisant 4 pixels voisins, offrant un bon compromis vitesse/qualité.
- BicubicResize.java : Utilise 16 pixels voisins pour une interpolation bicubique plus lisse et de meilleure qualité, mais plus lente.
- MultiStepResizeStrategy.java : Stratégie composite permettant d’appliquer plusieurs méthodes d’interpolation séquentiellement, utile pour des redimensionnements progressifs ou combinés.
- ResizeStrategy.java : Interface commune à toutes les stratégies d’interpolation, garantissant la cohérence des implémentations.
- ImageUtils.java : Classe utilitaire pour la manipulation d’images.

*Main (``fr.uge.lego.main``)*
- Main.java : Point d’entrée de l’application, permettant de configurer les chemins d’images et les stratégies d’interpolation avant d’exécuter le programme.

*Matrix (``fr.uge.lego.matrix``)*
- Matrix.java : Classe utilitaire pour la manipulation matricielle utilisée dans certains calculs d’interpolation.

## Choix d’implémentation

- L’architecture respecte une séparation claire entre la logique métier (backend), la communication avec l’usine (factory), et le traitement d’image (image).
- Le pattern Stratégie est utilisé pour les interpolations d’image via l’interface ResizeStrategy. Cela permet d’ajouter facilement de nouvelles méthodes d’interpolation sans modifier le code client.
- La classe MultiStepResizeStrategy permet d’appliquer plusieurs stratégies d’interpolation de façon séquentielle, offrant ainsi une flexibilité dans le processus de redimensionnement. Si le nombre d’étapes définies est insuffisant pour atteindre la taille finale, la dernière stratégie est répétée en boucle jusqu’à ce que le redimensionnement soit terminé.
- La gestion du stock et la communication avec l’usine sont uniquement architecturées, non implémentées complètement.

## Difficultés rencontrées

- L’implémentation de l’interpolation bicubique a été particulièrement complexe, notamment en raison des calculs matriciels et des interpolations sur 16 pixels voisins.
- Pour cette raison, l’aide d’une intelligence artificielle a été utilisée pour valider et corriger certaines parties du code.
- L'implémentation de la gestion du stock n'a pas été faite suite à la non compréhension de la consigne et au manque de temps.

## Limitations et bugs connus

- La gestion complète du stock de briques n’est pas encore implémentée. Seule l’architecture pour cette gestion existe actuellement.
- Certaines fonctionnalités du backend, comme le traitement complet des commandes, ne sont pas encore développées.
- L’application est fonctionnelle pour le traitement d’image, mais manque de fonctionnalités avancées telles que la gestion automatisée des ressources Lego.

## Guide d’utilisation

- Ouvrir le fichier Main.java dans le package fr.uge.lego.main.
- Modifier les chemins des images pour correspondre à celles présentes dans le dossier ressources.
- Choisir la ou les stratégies d’interpolation à utiliser (ex. Bicubic, Bilinear, etc.) dans le code.
- Lancer le programme
- Le programme va alors redimensionner l’image choisie.