# Cahier de tests  - TableauLEGO

**Date** : 2026-04-02  
**Version** : 2.0  
**Framework** : Vitest 3.2.4 + MongoDB Memory Server  
**Résultat global** : **265 tests / 265 pass / 37 fichiers / 0 échec**  
**Durée d'exécution** : ~9 secondes

---

## Sommaire

1. [Vue d'ensemble](#1-vue-densemble)
2. [Tests unitaires  - Moteur de jeu](#2-tests-unitaires--moteur-de-jeu)
3. [Tests unitaires  - Scoring](#3-tests-unitaires--scoring)
4. [Tests unitaires  - Système de fidélité](#4-tests-unitaires--système-de-fidélité)
5. [Tests unitaires  - Expiration et politique de fidélité](#5-tests-unitaires--expiration-et-politique-de-fidélité)
6. [Tests unitaires  - Contrats et catalogue](#6-tests-unitaires--contrats-et-catalogue)
7. [Tests d'intégration  - Mode Solo](#7-tests-dintégration--mode-solo)
8. [Tests d'intégration  - Mode Duplicate](#8-tests-dintégration--mode-duplicate)
9. [Tests d'intégration  - Fidélité FIFO et rédemption](#9-tests-dintégration--fidélité-fifo-et-rédemption)
10. [Tests d'intégration  - Modèles de données MongoDB](#10-tests-dintégration--modèles-de-données-mongodb)
11. [Tests d'intégration  - Pont PHP ↔ Node](#11-tests-dintégration--pont-php--node)
12. [Tests d'intégration  - API publiques](#12-tests-dintégration--api-publiques)
13. [Tests de validation  - Site e-commerce PHP](#13-tests-de-validation--site-e-commerce-php)
14. [Tests de validation  - Base de données SQL](#14-tests-de-validation--base-de-données-sql)
15. [Matrice de couverture](#15-matrice-de-couverture)

---

## 1. Vue d'ensemble

### Architecture testée

| Composant | Technologie | Couvert |
|---|---|---|
| Backend de jeu | Fastify / Node.js / TypeScript | Oui |
| Base de données jeu | MongoDB (Memory Server en test) | Oui |
| Contrats partagés | TypeScript (`game-contracts`) | Oui |
| Pont PHP ↔ Node | JWT HMAC-SHA256 | Oui |
| Site e-commerce PHP | PHP / MariaDB | Oui (validation statique) |
| Base de données e-commerce | MariaDB (schéma SQL) | Oui (validation structurelle) |
| Frontend React | React 19 / TypeScript | Indirect (contrats validés) |
| Algorithme de tiling C | C / Quadtree | Non compilable (dépendances manquantes) |

### Commande d'exécution

```bash
cd games_platform/apps/game-server
npx vitest run
```

---

## 2. Tests unitaires  - Moteur de jeu

### `boardEngine.test.ts`  - 4 tests

| ID | Test | Résultat |
|---|---|---|
| BE-001 | Déduplique les rotations identiques | PASS |
| BE-002 | Valide un placement dans les limites | PASS |
| BE-003 | Calcule les coordonnées correctement | PASS |
| BE-004 | Crée un plateau vide avec les bonnes dimensions | PASS |

### `boardEngine.extended.test.ts`  - 24 tests

| ID | Test | Résultat |
|---|---|---|
| BE-E01 à BE-E24 | Collisions, hydratation, sérialisation, placements limites, rotations multiples, formes lacunaires | PASS (24/24) |

### `deterministicSequence.test.ts`  - 2 tests

| ID | Test | Résultat |
|---|---|---|
| DS-001 | Génère une séquence reproductible depuis un seed | PASS |
| DS-002 | Distribution pondérée des couleurs | PASS |

### `deterministicSequence.extended.test.ts`  - 6 tests

| ID | Test | Résultat |
|---|---|---|
| DS-E01 à DS-E06 | Seeds différents, longueur de séquence, cohérence inter-appels | PASS (6/6) |

### `soloSessionSupport.test.ts`  - 16 tests

| ID | Test | Résultat |
|---|---|---|
| SS-001 à SS-016 | Résolution du seed, timer de tour, bonus de temps, état de la session | PASS (16/16) |

---

## 3. Tests unitaires  - Scoring

### `imageRebuildScore.test.ts`  - 1 test + `imageRebuildScore.extended.test.ts`  - 8 tests

| ID | Test | Résultat |
|---|---|---|
| IRS-001 | Score de référence pour un plateau complet | PASS |
| IRS-E01 à IRS-E08 | Plateau vide, correspondance partielle, pénalités, précision, couverture | PASS (8/8) |

### `lineBreakerScore.test.ts`  - 1 test + `lineBreakerScore.extended.test.ts`  - 7 tests

| ID | Test | Résultat |
|---|---|---|
| LBS-001 | Score cumulé pour lignes effacées | PASS |
| LBS-E01 à LBS-E07 | Combos, lignes multiples, score nul, bonus consécutifs | PASS (7/7) |

### `lineBreakerRules.test.ts`  - 4 tests + `lineBreakerRules.extended.test.ts`  - 10 tests

| ID | Test | Résultat |
|---|---|---|
| LBR-001 à LBR-004 | Détection lignes horizontales/verticales monochromes, effacement | PASS (4/4) |
| LBR-E01 à LBR-E10 | Lignes partielles, couleurs mélangées, tableaux complexes, cascade | PASS (10/10) |

---

## 4. Tests unitaires  - Système de fidélité

### `loyaltyRewards.test.ts`  - 3 tests

| ID | Test | Résultat |
|---|---|---|
| LR-001 | Calcul de base solo (participation + performance) | PASS |
| LR-002 | Application du minimum garanti | PASS |
| LR-003 | Bonus duplicate (victoire / défaite / égalité) | PASS |

### `loyaltyRewards.extended.test.ts`  - 20 tests

| ID | Test | Résultat |
|---|---|---|
| LR-E01 à LR-E20 | Caps de performance, bonus d'image, bonus de lignes, ajustement abandon, forfait, tous les modes et types de jeu | PASS (20/20) |

---

## 5. Tests unitaires  - Expiration et politique de fidélité

### `loyaltyExpiration.test.ts`  - 13 tests (NOUVEAU)

| ID | Test | Résultat |
|---|---|---|
| EXP-001 | `computeExpirationDate` retourne une date à 30 jours par défaut | PASS |
| EXP-002 | Respecte les jours personnalisés depuis la politique (7 jours) | PASS |
| EXP-003 | Gère une expiration à 1 jour | PASS |
| EXP-004 | Gère une expiration à 365 jours | PASS |
| EXP-005 | Retourne une chaîne ISO valide | PASS |
| EXP-006 | `calculateLoyaltyReward` inclut `expiresAt` dans la décision | PASS |
| EXP-007 | Expiration par défaut de 30 jours sans `policyConfig` | PASS |
| EXP-008 | Expiration personnalisée depuis `policyConfig` | PASS |
| EXP-009 | Multiplicateur 1.5x appliqué correctement | PASS |
| EXP-010 | Multiplicateur 2x appliqué correctement | PASS |
| EXP-011 | Minimum garanti respecté même avec multiplicateur bas (0.1x) | PASS |
| EXP-012 | Multiplicateur appliqué au mode duplicate | PASS |
| EXP-013 | Combinaison multiplicateur + expiration personnalisée | PASS |

### `loyaltyPolicyFetcher.test.ts`  - 5 tests (NOUVEAU)

| ID | Test | Résultat |
|---|---|---|
| PF-001 | Retourne les valeurs par défaut quand la politique PHP est `null` | PASS |
| PF-002 | Extrait les jours d'expiration depuis la politique PHP | PASS |
| PF-003 | Extrait le multiplicateur depuis la politique PHP | PASS |
| PF-004 | Gère le multiplicateur Happy Hour (1.5x) | PASS |
| PF-005 | Gère le multiplicateur Pause déjeuner (1.25x) | PASS |

---

## 6. Tests unitaires  - Contrats et catalogue

### `contractsValidation.test.ts`  - 15 tests (NOUVEAU)

| ID | Test | Résultat |
|---|---|---|
| CV-001 | `GameType` contient ImageRebuild et LineBreaker | PASS |
| CV-002 | `GameType` a exactement 2 types | PASS |
| CV-003 | `GameMode` contient Solo et Duplicate2P | PASS |
| CV-004 | `GameMode` a exactement 2 modes | PASS |
| CV-005 | `AuthSource` contient Guest et Php | PASS |
| CV-006 | `SessionStatus` contient tous les statuts requis | PASS |
| CV-007 | `LoyaltyEntryType` contient les 4 types d'écriture | PASS |
| CV-008 | `ConnectionState` contient les états de connexion | PASS |
| CV-009 | `PlayerSeatType` contient les types de siège | PASS |
| CV-010 | `EventType` a au moins 10 types d'événement | PASS |
| CV-011 | Le catalogue contient au moins 6 formes de briques | PASS |
| CV-012 | Chaque forme a les propriétés requises (shapeId, rotations) | PASS |
| CV-013 | Chaque rotation a des cellules avec coordonnées x,y valides | PASS |
| CV-014 | Le catalogue contient des briques lacunaires (non rectangulaires) | PASS |
| CV-015 | Chaque forme a un `shapeId` unique | PASS |

---

## 7. Tests d'intégration  - Mode Solo

### `soloImageRebuild.integration.test.ts`  - 1 test

| ID | Test | Résultat |
|---|---|---|
| SIR-001 | Flux complet : création, placement, finalisation d'une partie solo image rebuild | PASS |

### `soloImageRebuild.extended.integration.test.ts`  - 5 tests

| ID | Test | Résultat |
|---|---|---|
| SIR-E01 à SIR-E05 | Rejet placement invalide, abandon, authentification requise, session inexistante, résultat final | PASS (5/5) |

### `soloLineBreaker.integration.test.ts`  - 1 test

| ID | Test | Résultat |
|---|---|---|
| SLB-001 | Flux complet : création, placement, finalisation d'une partie solo line breaker | PASS |

### `soloLineBreaker.extended.integration.test.ts`  - 4 tests

| ID | Test | Résultat |
|---|---|---|
| SLB-E01 à SLB-E04 | Effacement de ligne, scoring en jeu, abandon, session inexistante | PASS (4/4) |

---

## 8. Tests d'intégration  - Mode Duplicate

### `duplicateRoom.integration.test.ts`  - 1 test

| ID | Test | Résultat |
|---|---|---|
| DR-001 | Flux complet : création de salon, rejoindre, prêt, démarrage | PASS |

### `duplicateRoom.extended.integration.test.ts`  - 3 tests

| ID | Test | Résultat |
|---|---|---|
| DR-E01 à DR-E03 | Refus de démarrage si pas prêt, code salon invalide, salon plein | PASS (3/3) |

### `duplicateImageRebuild.integration.test.ts`  - 1 test

| ID | Test | Résultat |
|---|---|---|
| DIR-001 | Match complet image rebuild en mode duplicate | PASS |

### `duplicateLineBreaker.integration.test.ts`  - 1 test

| ID | Test | Résultat |
|---|---|---|
| DLB-001 | Match complet line breaker en mode duplicate | PASS |

### `duplicateChat.integration.test.ts`  - 2 tests

| ID | Test | Résultat |
|---|---|---|
| DC-001 | Messages diffusés aux deux joueurs | PASS |
| DC-002 | Persistance et limite de longueur des messages | PASS |

### `duplicateReconnect.integration.test.ts`  - 2 tests

| ID | Test | Résultat |
|---|---|---|
| DRC-001 | Reconnexion dans le délai de grâce (60s) | PASS |
| DRC-002 | Forfait après expiration du délai de grâce | PASS |

### `duplicateLoyalty.integration.test.ts`  - 1 test

| ID | Test | Résultat |
|---|---|---|
| DL-001 | Historique et points de fidélité enregistrés pour les deux joueurs | PASS |

---

## 9. Tests d'intégration  - Fidélité FIFO et rédemption

### `loyaltyFifo.integration.test.ts`  - 8 tests (NOUVEAU)

| ID | Test | Résultat |
|---|---|---|
| FIFO-001 | Stocke `remainingPoints` lors de l'ajout d'une écriture positive | PASS |
| FIFO-002 | `getAvailableBalance` retourne la somme des points non expirés | PASS |
| FIFO-003 | Consomme les points FIFO par date d'expiration (les plus proches d'abord) | PASS |
| FIFO-004 | Ne consomme pas les points expirés | PASS |
| FIFO-005 | Consommation partielle d'une écriture | PASS |
| FIFO-006 | Gère les écritures sans `expiresAt` (n'expirent jamais) | PASS |
| FIFO-007 | Retourne 0 consommé pour un joueur sans écritures | PASS |
| FIFO-008 | `getAvailableBalance` retourne 0 pour un joueur sans écritures | PASS |

### `loyaltyRedeem.integration.test.ts`  - 7 tests

| ID | Test | Résultat |
|---|---|---|
| RED-001 | Rédemption d'un palier valide avec déduction exacte des points | PASS |
| RED-002 | Balance mise à jour après rédemption | PASS |
| RED-003 | Rejet si solde insuffisant | PASS |
| RED-004 | Rejet d'une valeur de palier invalide | PASS |
| RED-005 | Rejet sans authentification | PASS |
| RED-006 | Enregistrement de la référence commande dans les métadonnées | PASS |
| RED-007 | Rédemption du palier 2 avec solde suffisant | PASS |

### `loyaltyPolicyEndpoint.integration.test.ts`  - 4 tests (NOUVEAU)

| ID | Test | Résultat |
|---|---|---|
| PE-001 | Bootstrap retourne les paliers même si PHP est injoignable | PASS |
| PE-002 | Structure correcte des paliers (points + discountPercent) | PASS |
| PE-003 | Bootstrap retourne `maxDiscountPercent` | PASS |
| PE-004 | Endpoint redeem fonctionne en fallback sur les paliers par défaut | PASS |

---

## 10. Tests d'intégration  - Modèles de données MongoDB

### `databaseModels.integration.test.ts`  - 13 tests (NOUVEAU)

| ID | Test | Résultat |
|---|---|---|
| DB-001 | Création d'un joueur guest avec profil de fidélité par défaut | PASS |
| DB-002 | Récupération d'un profil joueur avec stats et fidélité | PASS |
| DB-003 | Application correcte du delta de fidélité | PASS |
| DB-004 | Enregistrement des statistiques de session complétée | PASS |
| DB-005 | Retourne `null` pour un joueur inconnu | PASS |
| DB-006 | Troncature de l'alias à 24 caractères | PASS |
| DB-007 | Génération d'alias pour entrée vide | PASS |
| DB-008 | Ajout d'une écriture ledger avec `expiresAt` | PASS |
| DB-009 | Ajout d'une écriture de rédemption (delta négatif, sans expiration) | PASS |
| DB-010 | Comptage des écritures par joueur | PASS |
| DB-011 | Tri des écritures par `createdAt` descendant | PASS |
| DB-012 | Création d'un historique complet avec métadonnées | PASS |
| DB-013 | Prévention des doublons sur `historyId` | PASS |

---

## 11. Tests d'intégration  - Pont PHP / Node

### `phpAuth.integration.test.ts`  - 1 test

| ID | Test | Résultat |
|---|---|---|
| PA-001 | Échange d'un JWT PHP signé contre un token Node sans stocker de PII | PASS |

### `phpAuth.extended.integration.test.ts`  - 5 tests

| ID | Test | Résultat |
|---|---|---|
| PA-E01 à PA-E05 | Token expiré, signature invalide, issuer incorrect, audience incorrecte, même playerId pour le même sujet | PASS (5/5) |

### `phpBridgeContract.test.ts`  - 7 tests (NOUVEAU)

| ID | Test | Résultat |
|---|---|---|
| PBC-001 | Accepte un JWT PHP valide et retourne un token Node | PASS |
| PBC-002 | Rejette un JWT expiré | PASS |
| PBC-003 | Rejette un JWT signé avec le mauvais secret | PASS |
| PBC-004 | Rejette un JWT avec le mauvais issuer | PASS |
| PBC-005 | Rejette un JWT avec la mauvaise audience | PASS |
| PBC-006 | Pseudonymise le sujet PHP (ne stocke pas le userId brut) | PASS |
| PBC-007 | Même sujet PHP donne toujours le même playerId (stabilité) | PASS |

---

## 12. Tests d'intégration  - API publiques

### `healthBootstrap.integration.test.ts`  - 6 tests

| ID | Test | Résultat |
|---|---|---|
| HB-001 | Endpoint `/health` retourne status ok | PASS |
| HB-002 | Bootstrap retourne la configuration complète | PASS |
| HB-003 | Bootstrap inclut les jeux et modes supportés | PASS |
| HB-004 | API puzzles retourne la liste | PASS |
| HB-005 | API puzzle detail retourne un puzzle valide | PASS |
| HB-006 | Authentification guest crée un joueur avec token | PASS |

### `historyLoyalty.integration.test.ts`  - 1 test

| ID | Test | Résultat |
|---|---|---|
| HL-001 | Persiste les points et expose l'historique enrichi | PASS |

---

## 13. Tests de validation  - Site e-commerce PHP

### `phpEcommerceValidation.test.ts`  - 52 tests (NOUVEAU)

#### Structure de fichiers  - 17 tests

| ID | Test | Résultat |
|---|---|---|
| PHP-F01 à PHP-F17 | Vérification de l'existence de chaque fichier requis (index, connexion, création, panier, commande, compte, points, API, config, includes) | PASS (17/17) |

#### Prévention injection SQL  - 7 tests

| ID | Test | Résultat |
|---|---|---|
| PHP-SQL01 | `connexion.php` utilise des requêtes préparées | PASS |
| PHP-SQL02 | `creation.php` utilise des requêtes préparées | PASS |
| PHP-SQL03 | `cart.php` utilise des requêtes préparées | PASS |
| PHP-SQL04 | `order.php` utilise des requêtes préparées | PASS |
| PHP-SQL05 | `my_account.php` utilise des requêtes préparées | PASS |
| PHP-SQL06 | `my_orders.php` utilise des requêtes préparées | PASS |
| PHP-SQL07 | `add_cart.php` utilise des requêtes préparées | PASS |

#### Protection CSRF  - 6 tests

| ID | Test | Résultat |
|---|---|---|
| PHP-CSRF01 à PHP-CSRF06 | Token CSRF présent dans les formulaires de connexion, inscription, commande, compte, mot de passe oublié, réinitialisation | PASS (6/6) |

#### Sécurité des sessions  - 2 tests

| ID | Test | Résultat |
|---|---|---|
| PHP-SEC01 | `session.php` active le flag HttpOnly | PASS |
| PHP-SEC02 | `session.php` définit la politique SameSite | PASS |

#### Sécurité des mots de passe  - 3 tests

| ID | Test | Résultat |
|---|---|---|
| PHP-PWD01 | `creation.php` utilise `password_hash` | PASS |
| PHP-PWD02 | `connexion.php` utilise `password_verify` | PASS |
| PHP-PWD03 | `creation.php` valide la complexité du mot de passe | PASS |

#### Prévention XSS  - 4 tests

| ID | Test | Résultat |
|---|---|---|
| PHP-XSS01 à PHP-XSS04 | `htmlspecialchars` utilisé dans cart, my_account, my_orders, mes_points | PASS (4/4) |

#### Intégration plateforme de jeux  - 5 tests

| ID | Test | Résultat |
|---|---|---|
| PHP-GAME01 | `games_api.php` génère un JWT avec structure correcte | PASS |
| PHP-GAME02 | `games_api.php` valide HTTPS en production | PASS |
| PHP-GAME03 | `games_api.php` cache les tokens avec TTL | PASS |
| PHP-GAME04 | `mes_points.php` affiche le solde de fidélité | PASS |
| PHP-GAME05 | `cart.php` intègre la rédemption de points | PASS |

#### Endpoint politique de fidélité  - 5 tests

| ID | Test | Résultat |
|---|---|---|
| PHP-POL01 | `loyalty_policy.php` retourne le type JSON | PASS |
| PHP-POL02 | Inclut les règles temporelles (happy hour, weekend) | PASS |
| PHP-POL03 | Inclut la configuration d'expiration | PASS |
| PHP-POL04 | Inclut les headers CORS | PASS |
| PHP-POL05 | Calcule le multiplicateur dynamiquement (heure/jour) | PASS |

---

## 14. Tests de validation  - Base de données SQL

### Inclus dans `phpEcommerceValidation.test.ts`  - 3 tests

| ID | Test | Résultat |
|---|---|---|
| DB-SQL01 | Le fichier `dump.sql` existe | PASS |
| DB-SQL02 | Contient les tables requises (USER, IMAGE, TILLING, ORDER_BILL, ADDRESS, CATALOG, INVENTORY, LOG) | PASS |
| DB-SQL03 | Utilise des clés étrangères pour l'intégrité référentielle | PASS |

---

## 15. Matrice de couverture

### Par composant fonctionnel

| Fonctionnalité | Unitaires | Intégration | Validation | Total |
|---|---|---|---|---|
| Moteur de plateau | 28 |  - |  - | 28 |
| Séquence déterministe | 8 |  - |  - | 8 |
| Support session solo | 16 |  - |  - | 16 |
| Scoring image rebuild | 9 |  - |  - | 9 |
| Scoring line breaker | 8 |  - |  - | 8 |
| Règles line breaker | 14 |  - |  - | 14 |
| Fidélité (calcul) | 23 |  - |  - | 23 |
| Expiration + multiplicateurs | 13 |  - |  - | 13 |
| Policy fetcher | 5 |  - |  - | 5 |
| Contrats + catalogue briques | 15 |  - |  - | 15 |
| Solo image rebuild |  - | 6 |  - | 6 |
| Solo line breaker |  - | 5 |  - | 5 |
| Duplicate rooms |  - | 4 |  - | 4 |
| Duplicate image rebuild |  - | 1 |  - | 1 |
| Duplicate line breaker |  - | 1 |  - | 1 |
| Chat duplicate |  - | 2 |  - | 2 |
| Reconnexion duplicate |  - | 2 |  - | 2 |
| Fidélité duplicate |  - | 1 |  - | 1 |
| FIFO consommation |  - | 8 |  - | 8 |
| Rédemption points |  - | 7 |  - | 7 |
| Policy endpoint |  - | 4 |  - | 4 |
| Modèles MongoDB |  - | 13 |  - | 13 |
| Auth PHP bridge |  - | 13 |  - | 13 |
| API publiques |  - | 7 |  - | 7 |
| Site PHP  - structure |  - |  - | 17 | 17 |
| Site PHP  - sécurité |  - |  - | 22 | 22 |
| Site PHP  - intégration jeux |  - |  - | 10 | 10 |
| Base de données SQL |  - |  - | 3 | 3 |
| **Total** | **139** | **74** | **52** | **265** |

### Par exigence du cahier des charges

| Exigence | Testée | Tests associés |
|---|---|---|
| Jeu 1  - Reproduction d'image | Oui | SIR-*, IRS-*, DIR-001 |
| Jeu 2  - Casse-briques de lignes | Oui | SLB-*, LBS-*, LBR-*, DLB-001 |
| Mode solo | Oui | SIR-*, SLB-*, SS-* |
| Mode duplicate (2 joueurs) | Oui | DR-*, DIR-*, DLB-*, DC-*, DRC-* |
| Même séquence pour les 2 joueurs | Oui | DS-*, DIR-001, DLB-001 |
| Salon d'attente + code | Oui | DR-001, DR-E01 |
| Chat textuel | Oui | DC-001, DC-002 |
| Reconnexion + forfait | Oui | DRC-001, DRC-002 |
| Briques lacunaires | Oui | CV-014 |
| Timer par tour | Oui | SS-* |
| Points de fidélité (attribution) | Oui | LR-*, EXP-*, DL-001 |
| Expiration des points | Oui | EXP-001 à EXP-008, FIFO-004 |
| FIFO par échéance | Oui | FIFO-001 à FIFO-008 |
| Politique fidélité JSON depuis PHP | Oui | PF-*, PE-*, PHP-POL* |
| Politique dynamique temporelle | Oui | PF-004, PF-005, PHP-POL02, PHP-POL05 |
| Historique des parties | Oui | HL-001, DB-012, DB-013 |
| Authentification guest | Oui | HB-006, DB-001 |
| Bridge PHP / Node (JWT) | Oui | PA-*, PBC-* |
| Pseudonymisation | Oui | PBC-006 |
| Stabilité du playerId | Oui | PBC-007 |
| Séparation données personnelles | Oui | PBC-006, PA-001 |
| SQL injection prevention (PHP) | Oui | PHP-SQL01 à PHP-SQL07 |
| CSRF protection (PHP) | Oui | PHP-CSRF01 à PHP-CSRF06 |
| XSS prevention (PHP) | Oui | PHP-XSS01 à PHP-XSS04 |
| Sécurité sessions (PHP) | Oui | PHP-SEC01, PHP-SEC02 |
| Sécurité mots de passe (PHP) | Oui | PHP-PWD01 à PHP-PWD03 |
| Schéma base de données SQL | Oui | DB-SQL01 à DB-SQL03 |
| Rédemption points sur boutique | Oui | RED-*, PHP-GAME05 |

---

## Annexe  - Fichiers de test

| Fichier | Tests | Type | Nouveau |
|---|---|---|---|
| `boardEngine.test.ts` | 4 | Unitaire | |
| `boardEngine.extended.test.ts` | 24 | Unitaire | |
| `deterministicSequence.test.ts` | 2 | Unitaire | |
| `deterministicSequence.extended.test.ts` | 6 | Unitaire | |
| `soloSessionSupport.test.ts` | 16 | Unitaire | |
| `imageRebuildScore.test.ts` | 1 | Unitaire | |
| `imageRebuildScore.extended.test.ts` | 8 | Unitaire | |
| `lineBreakerScore.test.ts` | 1 | Unitaire | |
| `lineBreakerScore.extended.test.ts` | 7 | Unitaire | |
| `lineBreakerRules.test.ts` | 4 | Unitaire | |
| `lineBreakerRules.extended.test.ts` | 10 | Unitaire | |
| `loyaltyRewards.test.ts` | 3 | Unitaire | |
| `loyaltyRewards.extended.test.ts` | 20 | Unitaire | |
| `loyaltyExpiration.test.ts` | 13 | Unitaire | Oui |
| `loyaltyPolicyFetcher.test.ts` | 5 | Unitaire | Oui |
| `contractsValidation.test.ts` | 15 | Unitaire | Oui |
| `soloImageRebuild.integration.test.ts` | 1 | Intégration | |
| `soloImageRebuild.extended.integration.test.ts` | 5 | Intégration | |
| `soloLineBreaker.integration.test.ts` | 1 | Intégration | |
| `soloLineBreaker.extended.integration.test.ts` | 4 | Intégration | |
| `duplicateRoom.integration.test.ts` | 1 | Intégration | |
| `duplicateRoom.extended.integration.test.ts` | 3 | Intégration | |
| `duplicateImageRebuild.integration.test.ts` | 1 | Intégration | |
| `duplicateLineBreaker.integration.test.ts` | 1 | Intégration | |
| `duplicateChat.integration.test.ts` | 2 | Intégration | |
| `duplicateReconnect.integration.test.ts` | 2 | Intégration | |
| `duplicateLoyalty.integration.test.ts` | 1 | Intégration | |
| `loyaltyFifo.integration.test.ts` | 8 | Intégration | Oui |
| `loyaltyRedeem.integration.test.ts` | 7 | Intégration | |
| `loyaltyPolicyEndpoint.integration.test.ts` | 4 | Intégration | Oui |
| `databaseModels.integration.test.ts` | 13 | Intégration | Oui |
| `phpAuth.integration.test.ts` | 1 | Intégration | |
| `phpAuth.extended.integration.test.ts` | 5 | Intégration | |
| `phpBridgeContract.test.ts` | 7 | Intégration | Oui |
| `healthBootstrap.integration.test.ts` | 6 | Intégration | |
| `historyLoyalty.integration.test.ts` | 1 | Intégration | |
| `phpEcommerceValidation.test.ts` | 52 | Validation | Oui |
| **Total** | **265** | | **8 nouveaux** |
