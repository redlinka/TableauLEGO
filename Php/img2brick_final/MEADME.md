# TableauLEGO- Plateforme Web de Mosaïques LEGO

Ce document présente l'architecture technique et les fonctionnalités clés de la composante Web (PHP) du projet **TableauLEGO**. Cette application permet aux utilisateurs de convertir des images en plans de construction LEGO, en s'appuyant sur une architecture hybride PHP/Java/C.

Si vous le souhaitez, le site est également accessible sur : *adam.nachnouchi.com/lego/*

Notez que le serveur Go installé en local servant à la commande de briques sera probablement éteint, mais pourra être démarré si vous le souhaitez.

## Architecture & Choix Techniques

Le projet repose sur une séparation claire entre le front-end/gestion de session (PHP) et le traitement algorithmique lourd (Java/C).

## Sécurité & Authentification

La sécurité a été une priorité dans la conception du parcours utilisateur :

* **Cloudflare Turnstile (Captcha) :** Intégration de l'API Turnstile pour protéger les formulaires de connexion et d'inscription contre les bots, sans nuire à l'expérience utilisateur (`validateTurnstile` dans `cnx.php`).
* **Authentification 2FA & Magic Links :** Le système n'utilise pas une simple connexion par mot de passe. Lors de la connexion ou de l'inscription, un lien unique et temporaire (token chiffré) est envoyé par email (via PHPMailer). Ce mécanisme vérifie l'identité de l'utilisateur et prévient les accès non autorisés.
* **Protection CSRF :** Implémentation de jetons CSRF (`csrf_get`, `csrf_validate`) avec rotation automatique (`csrf_rotate`) après chaque action sensible pour contrer les attaques de type *Cross-Site Request Forgery*.

## Gestion Avancée des Données & Nettoyage

L'application gère dynamiquement le cycle de vie des fichiers pour éviter la saturation du serveur.

### Gestion de l'Historique (Le "Back Button")
Une logique récursive stricte est appliquée à l'arbre de dépendance des images (`deleteDescendants`).
* **Comportement :** Si un utilisateur est à l'étape 3 (Filtres) et décide de revenir à l'étape 1 (Crop) pour modifier son image, le système détecte cette rupture de linéarité.
* **Conséquence :** Toutes les images et fichiers de pavage "enfants" (étapes 2, 3 et 4 générées précédemment) sont automatiquement supprimés du disque et de la base de données. Cela garantit la cohérence des données et évite les fichiers orphelins.

### Garbage Collection Automatique
Un système de nettoyage passif est intégré (`cleanStorage`) :
* **Fichiers temporaires :** Les images de traitement intermédiaires vieilles de plus de 30 minutes sont supprimées.
* **Sessions Invités abandonnées :** Les images générées par des utilisateurs non connectés sont purgées après 1 heure d'inactivité, maintenant le dossier `users/imgs/` propre.

## Fonctionnalités Clés

### 1. Pipeline de Traitement
Le parcours utilisateur suit 4 étapes distinctes, chacune sauvegardée en base de données :
1.  **Crop :** Recadrage via *CropperJS*.
2.  **Downscale :** Réduction de résolution via algorithme Java.
3.  **Filtres :** Application de nuances (Sépia, NB, etc.).
4.  **Pavage (Tiling) :** Conversion en briques LEGO via l'exécutable C, avec choix de la méthode (Quadtree, 1x1, etc.) et du budget.
Nous avons choisi ce flow car il semblait intuitif, linéaire et user-friendly. Cette approche a également permit de facilité le déboggage, étant donné qu'elle isole chaque étape de la conversion d'une image en briques.

### 2. Génération de Manuel PDF
Le script `generate_manual.php` utilise la librairie **FPDF** pour générer dynamiquement un livret d'instruction complet :
* Page de garde stylisée.
* Liste des pièces (Bill of Materials) avec identifiants visuels et couleurs hexadécimales.
* Plan de montage quadrillé pour l'assemblage physique.

### 3. Interface Administration
Un panneau d'administration (`admin_panel.php`) permet la gestion du système :
* **Visualisation des clients.** 
* **Suivi des commandes :** Visualisation des commandes clients et des statuts.
* **Gestion des Stocks & Réapprovisionnement :** L'admin peut lancer une commande de réassort ("Restock"). Le PHP génère un fichier de commande temporaire et invoque l'algorithme Java `ManualRestock` pour mettre à jour les stocks virtuels du catalogue.

## Dépendances
* **PHP 8.x** (Extensions : PDO, GD, OpenSSL)
* **Base de données :** MySQL/MariaDB
* **Java Runtime Environment (JRE)** pour l'exécution des JARs.
* **Composer** pour PHPMailer.