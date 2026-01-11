# Documentation de la Base de Données TableauLEGO

Ce dépôt contient la structure de la base de données MariaDB/MySQL pour le projet **Img2Brick**. Cette base gère le cycle de vie complet d'une usine de mosaïques de briques : authentification des utilisateurs, inventaire des pièces, génération de mosaïques (pavage), gestion des commandes et maintenance automatisée.

## Principes de Conception et Intégrité

### 1. Immutabilité et Protection des Données Historiques

L'architecture de la base de données est conçue pour garantir que l'historique financier et physique ne puisse pas être altéré, même par inadvertance.

* **Intégrité Référentielle (Constraints) :**
* Des contraintes de clés étrangères (`FOREIGN KEY`) strictes sont appliquées entre les tables `ORDERS`, `ORDER_ITEMS` et `INVENTORY`.
* **Prevention de Suppression :** Il est techniquement impossible de supprimer une brique de la table `INVENTORY` ou un utilisateur de la table `USERS` si ceux-ci sont liés à une commande passée. La base de données rejettera toute instruction `DELETE` qui violerait cette intégrité (comportement `ON DELETE RESTRICT`). Cela garantit qu'une facture générée restera toujours mathématiquement reconstructible.


* **Unicité du Catalogue :**
* La table `CATALOG` impose une contrainte d'unicité composite sur les champs `(width, height, depth, color_hex, holes)`.
* Une modification physique d'une brique (ex: changement de teinte) nécessite la création d'une nouvelle entrée ID, empêchant ainsi la corruption rétroactive des commandes précédentes qui utilisaient l'ancienne version.


* **Sécurité de l'Inventaire :**
* Chaque brique physique dans `INVENTORY` possède un certificat unique et un numéro de série. Une fois une brique vendue ou utilisée, son lien avec la commande est définitif.



### 2. Stockage Hybride (Absence de BLOBs)

Nous ne stockons pas les données binaires des images dans la base de données relationnelle pour éviter la dégradation des performances.

* **Approche Logique :** La table `PAVAGE` stocke uniquement une référence textuelle unique via le champ `file_name`.
* **Approche Physique :** Les fichiers d'instructions (`.csv`, `.png`) et les prévisualisations sont stockés sur le système de fichiers du serveur.
* **Avantage :** La base de données reste légère, facilitant les sauvegardes et la réplication, tandis que le système de fichiers gère le poids des médias.

### 3. Gestion des Paniers ("Draft Logic")

Il n'existe pas de table `CARTS` distincte. La logique repose sur l'état de la commande.

* **Mécanisme :** Le panier d'un utilisateur est simplement une entrée dans la table `ORDERS` où le champ `order_date` est `NULL`.
* **Flux :**
1. Ajout d'article : `INSERT INTO ORDERS` (avec date nulle).
2. Consultation du panier : `SELECT` filtré sur la date nulle.
3. Paiement : `UPDATE` attribuant la date actuelle (`NOW()`), transformant le brouillon en commande immuable.



### 4. Audit et Journalisation

Toutes les actions sensibles sont enregistrées dans la table `LOGS` selon une syntaxe linguistique stricte pour faciliter l'analyse syntaxique (parsing).

* **Format :** `<AGENT> / <ACTION> / <OBJECT>`
* **Exemples :**
* `user1321@gmail.com / logged / in`
* `user4562@gmail.com / created / tiling_45452`
* `admin@factory.com / restocked / order_99`



---

## Structure des Tables

### Gestion Utilisateurs

* **`USERS`** : Stocke les informations d'authentification. Les mots de passe sont hachés et salés (Argon2/BCrypt).
* **`2FA`** : Gère les codes d'authentification à double facteur avec une date d'expiration stricte.
* **`LOGS`** : Registre historique inaltérable des actions.

### Inventaire & Catalogue

* **`CATALOG`** : La définition abstraite des briques. Contient les dimensions, couleurs hexadécimales et types de trous. Ne contient pas les quantités.
* **`INVENTORY`** : Les briques physiques réelles.
* `pavage_id IS NULL` : La brique est en stock libre.
* `pavage_id IS NOT NULL` : La brique est réservée physiquement pour un projet spécifique.


* **`RESTOCK_ORDER` & `RESTOCK_ITEMS**` : Suivi des arrivages fournisseurs. Ces tables sont verrouillées par des clés étrangères pour empêcher la suppression d'un historique d'approvisionnement.

### Mosaïques & Commandes

* **`PAVAGE`** : Représente le projet de mosaïque généré. Lie le système de fichiers à l'utilisateur.
* **`ORDERS`** : Gère les transactions financières.
* **`ORDER_ITEMS`** : Table de liaison Many-to-Many entre les Commandes et le Catalogue.

---

## Vues (Views)

### `catalog_with_price_and_stock`

Cette vue constitue l'interface critique pour l'algorithme de pavage en C et le Frontend. Elle abstrait la complexité des jointures SQL.

* **Fonctionnalités :**
1. Calcule le prix dynamique de chaque brique via la fonction `calculate_brick_price`.
2. Compte le stock disponible en temps réel (éléments dont le `pavage_id` est NULL).
3. Formate les données pour correspondre exactement aux structures (`struct`) attendues par le programme C.


* **Note technique :** Utilise un `LEFT JOIN` avec la condition de disponibilité placée dans la clause de jointure pour ne pas exclure du catalogue les pièces dont le stock est temporairement épuisé.

---

## Programmabilité

### Fonctions

* **`calculate_brick_price(base, factor, width, height)`** :
* Implémente la courbe de tarification logarithmique.
* Formule : `Prix = Base * (Factor ^ log2(Width * Height))`
* Assure une dégressivité du prix au "stud" pour les grandes pièces, incitant à une optimisation du pavage.



### Triggers (Déclencheurs)

* **`prevent_catalog_modification`** : Un trigger `BEFORE UPDATE` sur `CATALOG`. Il lève une exception si une tentative est faite pour modifier les dimensions d'une brique existante, garantissant l'intégrité des commandes passées référençant cet ID.
* **`log_user_action`** : Automatise l'insertion dans `LOGS` lors d'actions critiques.
* **`cleanup_2fa`** : Supprime automatiquement les codes utilisés ou expirés après une connexion réussie.

---

## Maintenance et Politique de Rétention (GDPR)

Pour se conformer aux régulations (RGPD) et optimiser le stockage, la base utilise des évènements planifiés (`EVENTS`).

* **`delete_inactive_users`** :
* **Fréquence :** Quotidienne.
* **Action :** Identifie les utilisateurs inactifs depuis **3 ans**.
* **Conséquence :** Supprime le compte et les données personnelles. Une entrée anonymisée est ajoutée aux logs avant la suppression définitive.

---

## Pistes d'Amélioration Futures

1. **Vues Matérialisées pour le Stock :**
* Actuellement, le stock est calculé via un `COUNT(*)` dynamique. Pour passer à l'échelle (10M+ lignes), l'ajout d'une colonne `current_stock` dans `CATALOG`, mise à jour par triggers lors des mouvements dans `INVENTORY`, supprimerait la latence de comptage.


2. **Partitionnement de Table :**
* Partitionner la table `INVENTORY` par `id_catalogue` pour accélérer les requêtes de réservation et de réapprovisionnement.


3. **Soft Deletes :**
* Remplacer les instructions `DELETE` sur la table `PAVAGE` par une colonne `deleted_at`, permettant une restauration par l'utilisateur en cas d'erreur pendant 30 jours.