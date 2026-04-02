# DOSSIER DE CONCEPTION TECHNIQUE DETAILLEE

**Projet** : TableauLEGO  
**Date** : 2026-04-02  
**Version** : 1.0

---

## Table des matieres

1. [Introduction](#i-introduction)
2. [Architecture technico-fonctionnelle](#ii-architecture-technico-fonctionnelle)
3. [Description technique detaillee des traitements](#iii-description-technique-detaillee-des-traitements)
4. [Glossaire des termes](#iv-glossaire-des-termes)
5. [Annexe](#v-annexe)

---

## I. Introduction

### 1. Objectifs du projet

TableauLEGO est une plateforme web permettant de :

- **Generer des mosaiques LEGO** a partir d'images importees par les utilisateurs (pixellisation, tiling, commande)
- **Fideliser la clientele** via un systeme de gamification : deux jeux en ligne sur le theme des briques LEGO permettant de gagner des points de fidelite echangeables contre des reductions
- **Proposer une experience multi-support** : site e-commerce PHP, application de jeu React, communication temps reel via WebSocket

Le systeme est compose de deux parties independantes :
- Un **site e-commerce PHP** gerant les comptes clients, le catalogue, le panier, les commandes et le paiement
- Une **plateforme de jeux Node.js/React** gerant les jeux de fidelisation, le mode multijoueur et le systeme de points

Ces deux parties communiquent via un **pont JWT** securise, sans partage de donnees personnelles.

### 2. Demarche Agile-SCRUM

**Contexte SCRUM** :
- **Product Owner** : Olivier CHAMPALLE
- **Scrum Master** : Adam KADI
- **Equipe de developpement** : Adam KADI, Theo JULLIEN, Matheo LARIVIERE
- **Duree du projet** : 20 fevrier - 9 avril (~7 semaines, 8 sprints)

**Ceremonies SCRUM** :
- **Daily** : 10 min max (Qu'ai-je fait ? Que vais-je faire ? Blocages ?)
- **Sprint Planning** : debut de chaque sprint, selection du backlog
- **Sprint Review** : demonstration au Product Owner
- **Retrospective** : ameliorations d'equipe en fin de sprint

**Outils** :
- **Langages** : PHP 8, TypeScript 5.9, C, Java
- **Frameworks** : Fastify 5.6, React 19, Vite 7
- **Bases de donnees** : MariaDB 10.11, MongoDB 8.18 (Mongoose)
- **Temps reel** : WebSocket (ws 8.18)
- **Authentification** : JWT (jose 6.1), HMAC-SHA256
- **Tests** : Vitest 3.2, MongoDB Memory Server
- **Conteneurisation** : Docker (MongoDB local)
- **Depot** : Git monorepo avec separation par dossier

### 3. Product Backlog (User Stories)

**Jeux** :
- US1 : En tant que joueur, je veux placer une brique pour completer un tableau
- US2 : En tant que joueur, je veux voir mon score apres une partie
- US3 : En tant que joueur, je veux jouer en solo
- US4 : En tant que joueur, je veux jouer en mode 2 joueurs (duplicate)
- US5 : En tant que joueur, je veux voir le plateau de mon adversaire
- US6 : En tant que joueur, je veux discuter avec l'autre joueur

**Backend / Systeme** :
- US7 : En tant que systeme, je veux generer des parties avec WebSocket
- US8 : En tant que systeme, je veux stocker les scores et historiques (MongoDB)
- US9 : En tant que systeme, je veux gerer les points de fidelite
- US10 : En tant que systeme, je veux exposer une API pour le site PHP

**Mobile** :
- US11 : En tant qu'utilisateur, je veux recevoir des notifications
- US12 : En tant qu'utilisateur, je veux jouer depuis l'application mobile
- US13 : En tant qu'utilisateur, je veux rester connecte automatiquement

**Web / UX** :
- US14 : En tant qu'utilisateur, je veux une interface responsive
- US15 : En tant qu'utilisateur, je veux voir l'historique de mes parties

**Communication (R4.06)** :
- US16 : Creer identite entreprise (logo, slogan)
- US17 : Plan de communication
- US18 : Indicateurs et budget

### 4. Planning  - Sprints

#### Sprint 0 (20 - 23 fevrier)  - Setup

| Tache | Assignation |
|---|---|
| Initialisation repo + architecture | Adam |
| Setup backend Node / Mongo | Theo |
| Setup frontend React | Matheo |

Jalon : environnement pret.

#### Sprint 1 (24 fevrier - 2 mars)  - Core Game

Objectif : jeu solo fonctionnel.

| Tache | Assignation |
|---|---|
| Implementation grille + placement briques | Adam |
| Backend API (creation partie) | Theo |
| Interface React de base | Matheo |

Review : 2 mars. Jalon : jeu jouable.

#### Sprint 2 (3 - 9 mars)  - Backend + BDD

Objectif : persistance et structure serveur.

| Tache | Assignation |
|---|---|
| MongoDB : schema (scores, parties) | Adam |
| API Node.js + WebSocket base | Theo |
| Integration frontend | Matheo |

Jalon : backend connecte.

#### Sprint 3 (10 - 16 mars)  - Multijoueur

Objectif : mode duplicate fonctionnel.

| Tache | Assignation |
|---|---|
| Logique synchronisation tours | Adam |
| WebSocket avance (salon, chat) | Theo |
| UI multiplayer | Matheo |

Review : demo multi. Jalon : multijoueur OK.

#### Sprint 4 (17 - 23 mars)  - Fidelite

Objectif : gamification complete.

| Tache | Assignation |
|---|---|
| Logique scoring fidelite | Adam |
| API points pour PHP | Theo |
| Affichage historique | Matheo |

Jalon : systeme de fidelite OK.

#### Sprint 5 (24 - 30 mars)  - Mobile

Objectif : application Android.

| Tache | Assignation |
|---|---|
| Coordination + integration | Adam |
| API notifications | Theo |
| Application Android | Matheo |

Review : demo mobile.

#### Sprint 6 (31 mars - 6 avril)  - Qualite et communication

Objectif : finalisation.

| Tache | Assignation |
|---|---|
| Documentation + QA | Adam |
| Optimisation backend | Theo |
| UI finale | Matheo |

Jalon : version finale.

#### Sprint 7 (7 - 9 avril)  - Livraison

Objectif : soutenance.

| Tache | Assignation |
|---|---|
| Presentation + Scrum | Adam |
| Demo backend | Theo |
| Demo frontend/mobile | Matheo |

Livraison finale : 9 avril.

### 5. Jalons importants

| Jalon | Sprint | Date |
|---|---|---|
| Environnement pret | Sprint 0 | 23 fevrier |
| Jeu jouable en solo | Sprint 1 | 2 mars |
| Backend connecte | Sprint 2 | 9 mars |
| Mode multijoueur OK | Sprint 3 | 16 mars |
| Systeme de fidelite OK | Sprint 4 | 23 mars |
| Application mobile OK | Sprint 5 | 30 mars |
| Projet complet | Sprint 6 | 6 avril |
| Soutenance prete | Sprint 7 | 9 avril |

---

## II. Architecture technico-fonctionnelle

### 1. Vue d'ensemble

```
┌─────────────────────────────────────────────────────────────────┐
│                        NAVIGATEUR CLIENT                        │
│  ┌─────────────────────┐     ┌────────────────────────────────┐ │
│  │ Site PHP (boutique)  │     │  App React (jeux de fidelite) │ │
│  │ - Catalogue          │     │  - Image Rebuild (solo/duo)   │ │
│  │ - Panier / Commande  │     │  - Line Breaker (solo/duo)    │ │
│  │ - Compte utilisateur │     │  - Historique / Points         │ │
│  │ - Points de fidelite │     │  - Chat duplicate             │ │
│  └────────┬─────────────┘     └──────┬──────────┬─────────────┘ │
└───────────┼──────────────────────────┼──────────┼───────────────┘
            │ HTTP                     │ HTTP     │ WebSocket
            ▼                          ▼          ▼
┌───────────────────┐        ┌──────────────────────────────────┐
│ Serveur PHP       │  JWT   │  Backend Node.js (Fastify)       │
│ (Apache/Nginx)    │◄──────►│  - API REST /api/v1/*            │
│                   │        │  - WebSocket /ws                 │
│ - Auth + 2FA      │        │  - Moteur de jeu                 │
│ - CRUD commandes  │        │  - Scoring + fidelite            │
│ - Tiling (C/Java) │        │  - Sessions solo + duplicate     │
│ - API politique    │        │  - Pont PHP (echange JWT)        │
└────────┬──────────┘        └──────────┬───────────────────────┘
         │                              │
         ▼                              ▼
┌───────────────────┐        ┌──────────────────────────────────┐
│ MariaDB           │        │  MongoDB                         │
│ - USER            │        │  - players                       │
│ - CATALOG (11662) │        │  - game_sessions                 │
│ - INVENTORY (3.6M)│        │  - game_events                   │
│ - ORDER_BILL      │        │  - chat_messages                 │
│ - IMAGE / TILLING │        │  - history_entries               │
│ - ADDRESS / LOG   │        │  - loyalty_ledger                │
└───────────────────┘        └──────────────────────────────────┘
```

### 2. Flux d'authentification

```
Visiteur (guest)                    Utilisateur PHP
     │                                    │
     ▼                                    ▼
POST /auth/guest              PHP genere JWT signe (HS256)
     │                          iss: tableaulego-php
     ▼                          sub: userId (chiffre)
Token Node.js                   exp: +5 minutes
(playerId = ply_UUID)                  │
                                       ▼
                              POST /auth/php/exchange
                                       │
                                       ▼
                              Token Node.js
                              (playerId = HMAC(sub))
                              Pseudonymise, pas de PII
```

### 3. Flux de jeu  - Mode Solo

```
Client                        Serveur
  │                              │
  │  POST /solo/*/sessions       │
  │  {puzzleId, seed?}           │
  │─────────────────────────────►│ Cree session + sequence seedee
  │                              │
  │  ◄── Etat initial ──────────│
  │                              │
  │  POST /solo/*/placements     │
  │  {anchorX, anchorY, rotation}│
  │─────────────────────────────►│ Valide placement
  │                              │ Met a jour plateau + score
  │  ◄── Nouvel etat ───────────│
  │                              │
  │  ... (repeter par tour) ...  │
  │                              │
  │  GET /solo/*/result          │
  │─────────────────────────────►│ Calcule score final
  │                              │ Attribue points fidelite
  │  ◄── Resultat + points ─────│ Enregistre historique
```

### 4. Flux de jeu  - Mode Duplicate (WebSocket)

```
Joueur 1 (Host)          Serveur            Joueur 2 (Guest)
     │                      │                      │
     │  room.create         │                      │
     │─────────────────────►│                      │
     │  ◄── room.created    │                      │
     │  (roomCode: ABC123)  │                      │
     │                      │   room.join          │
     │                      │◄─────────────────────│
     │  ◄── room.player_joined                     │
     │                      │── room.joined ──────►│
     │                      │                      │
     │  room.set_ready      │   room.set_ready     │
     │─────────────────────►│◄─────────────────────│
     │                      │                      │
     │  room.start          │                      │
     │─────────────────────►│                      │
     │  ◄── game.turn_started ─── (broadcast) ───►│
     │  (meme brique pour les 2)                   │
     │                      │                      │
     │  game.place_brick    │   game.place_brick   │
     │─────────────────────►│◄─────────────────────│
     │                      │                      │
     │  ◄── game.turn_resolved ── (broadcast) ───►│
     │  (les 2 plateaux mis a jour)                │
     │                      │                      │
     │  ◄── game.finished ────── (broadcast) ────►│
     │  (scores + points fidelite pour chacun)     │
```

### 5. Systeme de fidelite

```
┌─────────────────────────────────────────────────────────┐
│                   POLITIQUE (PHP → Node)                 │
│  GET /api/loyalty_policy.php                            │
│  {                                                      │
│    expiration: { defaultDays: 30 },                     │
│    temporalRules: [                                     │
│      { id: "happy_hour", hours: [18-21], mult: 1.5 },  │
│      { id: "weekend",    days: [6,7],    mult: 2.0 },  │
│      { id: "lunch",      hours: [12-13], mult: 1.25 }  │
│    ],                                                   │
│    tiers: [ 200→5%, 500→10%, 1000→20% ]                 │
│  }                                                      │
└────────────────────────┬────────────────────────────────┘
                         │ Node fetche (cache 5 min)
                         ▼
┌─────────────────────────────────────────────────────────┐
│                  ATTRIBUTION (Node)                      │
│  Formule loyalty_v1 :                                   │
│  points = max(minimum, (participation + performance     │
│           + outcomeBonus + gameBonus + adjust) * mult)   │
│  expiresAt = now + policy.defaultDays                   │
│  → Ledger entry avec remainingPoints + expiresAt        │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│                  CONSOMMATION (Node, appele par PHP)     │
│  POST /loyalty/redeem { points: 200|500|1000 }          │
│  1. getAvailableBalance (exclut les points expires)     │
│  2. consumePointsFifo (tri expiresAt ASC)               │
│  3. Debit du solde joueur                               │
│  4. Ledger entry de type Redemption                     │
└─────────────────────────────────────────────────────────┘
```

---

## III. Description technique detaillee des traitements

### 1. Modele Conceptuel de Donnees (MCD)  - E-commerce (MariaDB)

```
                    ┌──────────┐
                    │   USER   │
                    │──────────│
                    │ user_id  │
                    │ email    │
                    │ password │
                    │ username │
                    └────┬─────┘
                         │ 1
            ┌────────────┼────────────┬───────────────┐
            │ *          │ *          │ *             │ *
     ┌──────┴──┐   ┌─────┴────┐  ┌───┴──────┐  ┌────┴───┐
     │ ADDRESS │   │  IMAGE   │  │ORDER_BILL│  │  LOG   │
     │─────────│   │──────────│  │──────────│  │────────│
     │street   │   │filename  │  │created_at│  │agent   │
     │city     │   │path      │  │address_id│  │action  │
     │postal   │   │img_parent│  └────┬─────┘  │object  │
     └─────────┘   └────┬─────┘       │ *      └────────┘
                        │ 1       ┌───┴────┐
                   ┌────┴────┐    │contain │
                   │ TILLING │    │────────│
                   │─────────│◄───┤order_id│
                   │pavage_id│    │pavage_id
                   │pavage_txt    └────────┘
                   └────┬────┘
                        │ *
                   ┌────┴──────┐
                   │ INVENTORY │
                   │───────────│      ┌──────────┐
                   │serial_num │      │ CATALOG  │
                   │certificate│◄─────┤──────────│
                   │id_catalogue      │height    │
                   └───────────┘      │width     │
                                      │color_hex │
                                      │holes     │
                                      └──────────┘
```

### 2. Modele Conceptuel de Donnees (MCD)  - Jeux (MongoDB)

```
     ┌──────────────┐
     │   players    │
     │──────────────│
     │ playerId     │         ┌─────────────────┐
     │ authSource   │    *    │  game_sessions   │
     │ publicAlias  │◄────────┤─────────────────│
     │ loyaltySumm. │         │ sessionId       │
     │ statsSummary │         │ gameType        │
     └──────┬───────┘         │ mode            │
            │                 │ status          │
            │ 1               │ seed            │
            │           *     │ roomCode        │
     ┌──────┴──────────┐      │ players[]       │
     │ loyalty_ledger  │      │ sessionState    │
     │─────────────────│      └───────┬─────────┘
     │ entryId         │              │ 1
     │ pointsDelta     │         ┌────┴──────────┐
     │ remainingPoints │         │ game_events   │
     │ expiresAt       │         │───────────────│
     │ entryType       │         │ eventId       │
     │ reason          │         │ type          │
     └─────────────────┘         │ payload       │
                                 └───────────────┘
     ┌─────────────────┐    ┌────────────────────┐
     │ history_entries │    │  chat_messages     │
     │─────────────────│    │────────────────────│
     │ historyId       │    │ messageId          │
     │ score           │    │ sessionId          │
     │ rewardPoints    │    │ playerId           │
     │ outcome         │    │ content            │
     │ metadata        │    │ createdAt          │
     └─────────────────┘    └────────────────────┘
```

### 3. Modele Logique de Donnees (MLD)  - MariaDB

```
USER (user_id PK, email UNIQUE NOT NULL, password NOT NULL, username,
      first_name, last_name, phone UNIQUE, birth_year, is_verified NOT NULL,
      default_address UNIQUE, created_at)

2FA (id_token PK, verification_token, token_expire_at,
     user_id FK→USER UNIQUE)

ADDRESS (address_id PK, street, postal_code, city, country,
         user_id FK→USER NOT NULL)

IMAGE (image_id PK, filename NOT NULL, path NOT NULL, width, height,
       created_at, img_parent FK→IMAGE, user_id FK→USER, status ENUM)

TILLING (pavage_id PK, pavage_txt NOT NULL, image_id FK→IMAGE)

CATALOG (id_catalogue PK, height NOT NULL, width NOT NULL, color_hex,
         color_name, holes, UNIQUE(width, height, holes, color_name, color_hex))

INVENTORY (id_inventory PK, certificate UNIQUE, serial_num UNIQUE,
           pavage_id FK→TILLING ON DELETE SET NULL,
           id_catalogue FK→CATALOG NOT NULL)

ORDER_BILL (order_id PK, created_at, address_id FK→ADDRESS NOT NULL,
            user_id FK→USER NOT NULL)

contain (order_id FK→ORDER_BILL, pavage_id FK→TILLING,
         PK(order_id, pavage_id))

STOCK_ENTRY (id_stock_entry PK, date_stock)

entry (id_catalogue FK→CATALOG, id_stock_entry FK→STOCK_ENTRY,
       quantity, total_price, PK(id_catalogue, id_stock_entry))

LOG (id PK, agent, log_action, log_object, log_date,
     user_id FK→USER NOT NULL)
```

### 4. Modele Physique de Donnees (MPD)  - MongoDB

```
Collection: players
  playerId       String   UNIQUE INDEX
  authSource     String   ENUM [guest, php]
  publicAlias    String
  externalRef    { issuer: String, subject: String }
  technicalLoyaltyId  String
  lastSeenAt     Date     INDEX
  statsSummary   { totalSessions, totalScore, wins, losses, lastPlayedAt }
  loyaltySummary { balance, lifetimeEarned }
  timestamps     auto (createdAt, updatedAt)

Collection: game_sessions
  sessionId      String   UNIQUE INDEX
  mode           String   ENUM [solo, duplicate_2p]
  gameType       String   ENUM [image_rebuild, line_breaker]
  status         String   ENUM [waiting, active, completed, abandoned]
  rulesetVersion String
  seed           String
  roomCode       String   UNIQUE SPARSE INDEX
  hostPlayerId   String
  players        Array [{ seat, playerId, publicAlias, connectionState, ready, score, resumeTokenHash }]
  sessionState   Mixed
  result         Mixed
  startedAt      Date
  endedAt        Date
  INDEX: { "players.playerId": 1, createdAt: -1 }
  INDEX: { status: 1, updatedAt: -1 }

Collection: loyalty_ledger
  entryId        String   UNIQUE INDEX
  playerId       String
  sessionId      String
  entryType      String   ENUM [session_reward, duplicate_bonus, admin_adjustment, redemption]
  pointsDelta    Number
  balanceAfter   Number
  remainingPoints Number
  expiresAt      Date
  reason         String
  gameType       String
  mode           String
  outcome        String
  score          Number
  metadata       Mixed
  INDEX: { playerId: 1, createdAt: -1 }
  INDEX: { playerId: 1, expiresAt: 1, remainingPoints: 1 }

Collection: history_entries
  historyId      String   UNIQUE INDEX
  playerId       String
  sessionId      String
  gameType       String
  mode           String
  outcome        String   ENUM [win, loss, draw, solo, abandoned, forfeit]
  score          Number
  rewardPoints   Number
  startedAt      Date
  endedAt        Date
  metadata       Mixed
  INDEX: { playerId: 1, endedAt: -1 }

Collection: game_events
  eventId        String   UNIQUE INDEX
  sessionId      String
  seq            Number
  type           String   ENUM [14 types d'evenement]
  actorPlayerId  String
  payload        Mixed
  INDEX: { sessionId: 1, seq: 1 } UNIQUE

Collection: chat_messages
  messageId      String   UNIQUE INDEX
  sessionId      String
  playerId       String
  publicAlias    String
  content        String
  createdAt      Date
  INDEX: { sessionId: 1, createdAt: 1 }
```

### 5. Description detaillee fonctionnalite par fonctionnalite

#### 5.1 Jeu 1  - Reproduction d'image

**Objectif** : Reproduire une image cible pixellisee en placant des briques LEGO sur un tableau 10x10.

**Flux technique** :
1. Le pipeline `image_puzzle_maker` pre-genere des puzzles : image source → crop centre → resize 10x10 → mapping palette LEGO (12 couleurs via distance CIE Lab)
2. Le serveur charge les puzzles depuis `manifest.json` au demarrage
3. A la creation de session, une sequence de briques est generee (PRNG seed FNV-1a)
4. Chaque tour : le serveur propose une brique (forme + couleur), le joueur choisit position + rotation
5. Validation cote serveur : bounds check + collision detection via `boardEngine.ts`
6. Scoring : `matchedCells * 10 + coverageRatio * 40 + accuracyRatio * 80 - mismatchPenalty * 6`
7. Fin de partie : sequence epuisee, abandon, ou aucun placement possible

**Fichiers cles** :
- `domain/image-rebuild/soloSessionService.ts`  - Logique de session
- `domain/image-rebuild/score.ts`  - Calcul du score de fidelite
- `domain/common/boardEngine.ts`  - Moteur de plateau (placement, validation)
- `domain/common/deterministicSequence.ts`  - Generation de sequence seedee

#### 5.2 Jeu 2  - Casse-briques de lignes

**Objectif** : Placer des briques pour former des lignes monochromes completes (horizontales ou verticales) qui sont ensuite effacees.

**Flux technique** :
1. Plateau 10x10 vide, sequence de briques generee avec couleurs ponderees
2. Chaque tour : une brique est proposee (incluant des formes lacunaires : L, T, zigzag, U, frame)
3. Le joueur place la brique librement (pas de gravite), rotation autorisee
4. Apres placement : detection des lignes monochromes completes (`detectCompletedMonochromeLines`)
5. Effacement des lignes detectees : les cellules concernees sont videes, les briques traversantes sont brisees
6. Scoring : points de base + bonus quadratique multi-lignes (`linesCleared^2 * 10`) + bonus combo consecutif
7. Fin : abandon, aucun placement possible, ou sequence epuisee

**Fichiers cles** :
- `domain/line-breaker/soloSessionService.ts`  - Logique de session
- `domain/line-breaker/lineRules.ts`  - Detection et effacement des lignes
- `domain/line-breaker/score.ts`  - Calcul du score

#### 5.3 Mode Duplicate (2 joueurs)

**Objectif** : Les deux joueurs jouent sur leur propre plateau avec les memes briques proposees a chaque tour.

**Flux technique** :
1. Joueur 1 (host) cree un salon via WebSocket → code 6 caracteres genere
2. Joueur 2 rejoint avec le code (saisie manuelle ou URL `#roomCode=XXXX`)
3. Phase d'attente : chat disponible, les joueurs marquent "pret"
4. Le host lance la partie : meme seed pour les 2 joueurs
5. Chaque tour : timer partage (30s par defaut), les deux joueurs placent independamment
6. Le tour se resout quand les deux ont joue ou quand le timer expire (auto-placement)
7. Si un joueur se deconnecte : delai de grace 60s, puis forfait
8. Fin : les deux scores sont compares, points de fidelite attribues a chacun

**Fichiers cles** :
- `domain/duplicate/duplicateSessionService.ts`  - Orchestration complete
- `domain/duplicate/roomCode.ts`  - Generation de code
- `modules/websocket/socketHub.ts`  - Gestion des connexions et messages

#### 5.4 Systeme de fidelite

**Attribution** :
- Formule `loyalty_v1` appliquee a chaque fin de partie
- Solo : participation (10) + performance (0-30) + bonus jeu (0-20) + ajustement abandon (-4), minimum 6
- Duplicate : participation (12) + performance (0-20) + issue (2-18) + bonus jeu (0-10), minimum 2-8
- Multiplicateur temporel applique (happy hour ×1.5, weekend ×2, midi ×1.25)
- Expiration : 30 jours par defaut (configurable via politique PHP)

**Consommation FIFO** :
- Les points d'echeance la plus proche sont consommes en priorite
- Requete MongoDB : tri `expiresAt ASC, createdAt ASC`
- Les points expires (`expiresAt < now`) sont exclus du solde disponible
- Deduction partielle possible sur une ecriture

**Paliers de reduction** :
- 200 points → 5% de reduction
- 500 points → 10% de reduction
- 1000 points → 20% de reduction

**Fichiers cles** :
- `domain/common/loyalty.ts`  - Formule de calcul + expiration
- `domain/common/loyaltyPolicyFetcher.ts`  - Fetch politique depuis PHP
- `storage/mongoose/repositories/LoyaltyLedgerRepository.ts`  - FIFO + solde
- `modules/loyalty/routes.ts`  - API balance, ledger, redeem
- `Php/img2brick_final/api/loyalty_policy.php`  - Endpoint politique JSON

#### 5.5 Site e-commerce PHP

**Authentification** :
- Inscription avec validation email (lien magique, expiration 1 min)
- Connexion avec 2FA par email, protection brute-force (delai 150ms), captcha Cloudflare Turnstile
- Mot de passe : 12+ caracteres, majuscule, minuscule, chiffre, caractere special, hash bcrypt
- Sessions : HttpOnly, SameSite=Strict, regeneration a chaque authentification

**Panier et commandes** :
- Panier = ORDER_BILL avec `created_at IS NULL` (draft)
- Ajout au panier declenche le tiling Java (`brain.jar`)
- Commande : choix d'adresse, methode de paiement (carte test / PayPal sandbox), captcha
- Adresses figees au moment de la commande (snapshot immutable)

**Integration fidelite** :
- `cart.php` affiche le solde de points via `games_get_balance()`
- Le client choisit un palier de reduction lors du passage de commande
- `games_redeem_points()` appelle `POST /api/v1/loyalty/redeem` sur le backend Node

**Securite** :
- 146 requetes preparees (PDO), zero concatenation SQL
- Protection CSRF sur tous les formulaires (generation, validation, rotation)
- Encodage XSS via `htmlspecialchars()` sur toutes les sorties
- Validation MIME + extension + dimensions pour les uploads d'images

#### 5.6 Pipeline de generation de puzzles

**Etapes** :
1. Image source (PNG/JPG/WebP) → crop centre carre
2. Redimensionnement a 10x10 pixels
3. Chaque pixel mappe a la couleur la plus proche de la palette LEGO (12 couleurs, distance CIE Lab)
4. Export : JSON (grille `cells[][]`), preview PNG, debug image, heuristique de difficulte
5. `manifest.json` regroupe tous les puzzles generes

**Palette LEGO** (12 couleurs) : white, light_gray, dark_gray, black, red, orange, yellow, green, blue, dark_blue, tan, brown

#### 5.7 Algorithme de tiling C

- Decomposition quadtree de l'image
- Matching des regions avec le catalogue de briques LEGO
- Gestion du stock (mode strict vs relax)
- Optimisation cout/qualite avec biais de prix
- Sortie : liste de briques avec positions et couleurs

#### 5.8 Controles frontend

**Souris/Tactile** : clic pour placer, survol pour previsualisation fantome, boutons de rotation

**Clavier** :
- Fleches directionnelles : deplacer le curseur sur le plateau
- R : tourner la brique (rotation suivante)
- Entree / Espace : confirmer le placement

**Responsive** : media queries a 1180px, 840px et 520px, grille CSS dynamique, cellules carrees (`aspect-ratio: 1/1`)

---

## IV. Glossaire des termes

| Terme | Definition |
|---|---|
| **Tiling / Pavage** | Decomposition d'une image en briques LEGO rectangulaires ou lacunaires |
| **Brique lacunaire** | Brique dont la forme n'est pas un rectangle plein (ex: L, T, U, cadre) |
| **Seed** | Graine aleatoire utilisee pour generer une sequence de briques deterministe et reproductible |
| **Duplicate** | Mode de jeu a 2 joueurs avec la meme sequence de briques et des plateaux independants |
| **FIFO** | First In First Out  - les points de fidelite expirant le plus tot sont consommes en priorite |
| **Pont JWT** | Mecanisme d'authentification securise entre PHP et Node.js via tokens signes HMAC-SHA256 |
| **Pseudonymisation** | Transformation de l'identifiant PHP en hash irreversible cote Node pour proteger les donnees |
| **Ledger** | Journal des ecritures de points de fidelite (gains, consommations, ajustements) |
| **PII** | Personally Identifiable Information  - donnees personnelles (email, nom, telephone) |
| **Board Engine** | Moteur gerant la logique du plateau de jeu (creation, placement, validation, serialisation) |
| **Room / Salon** | Session de jeu multijoueur avec code d'acces, phase d'attente et chat |
| **Grace period** | Delai de grace apres deconnexion d'un joueur avant declaration de forfait (60s) |
| **Ghost cells** | Previsualisation en transparence de la position de la brique avant placement |
| **Turnstile** | Captcha Cloudflare utilise pour proteger les formulaires publics |
| **Bootstrap** | Endpoint renvoyant la configuration initiale (jeux, palettes, paliers, briques) |
| **Quadtree** | Structure arborescente divisant recursivement l'image en 4 quadrants pour le tiling |

---

## V. Annexe

### 1. Schema de base de donnees MariaDB

Le schema complet est disponible dans `DB/dump.sql` (569 Ko).

**Tables principales** : USER, 2FA, ADDRESS, CATALOG (11 662 references), INVENTORY (3 630 823 pieces), IMAGE, TILLING, ORDER_BILL, contain, STOCK_ENTRY, entry, LOG

**Vue** : `catalog_with_price_and_stock`  - jointure catalogue, stock et fonction de prix

**Triggers** : protection d'immutabilite sur ADDRESS, TILLING, INVENTORY, ORDER_BILL (empechent modification/suppression apres finalisation de commande)

### 2. Collections MongoDB

6 collections : `players`, `game_sessions`, `game_events`, `chat_messages`, `history_entries`, `loyalty_ledger`

Schema detaille en section III.4.

### 3. API REST  - Liste complete

| Methode | Endpoint | Auth | Description |
|---|---|---|---|
| GET | /health | Non | Sante du service |
| GET | /api/v1/bootstrap | Non | Configuration initiale |
| POST | /api/v1/auth/guest | Non | Creer un joueur invite |
| POST | /api/v1/auth/php/exchange | Non | Echanger un JWT PHP |
| GET | /api/v1/puzzles | Non | Liste des puzzles |
| GET | /api/v1/puzzles/:id | Non | Detail d'un puzzle |
| GET | /api/v1/history | Oui | Historique des parties |
| GET | /api/v1/history/:historyId | Oui | Detail d'une partie |
| GET | /api/v1/loyalty/balance | Oui | Solde de fidelite |
| GET | /api/v1/loyalty/ledger | Oui | Journal des ecritures |
| POST | /api/v1/loyalty/redeem | Oui | Echanger des points |
| POST | /api/v1/solo/image-rebuild/sessions | Oui | Creer partie image |
| GET | /api/v1/solo/image-rebuild/sessions/:id | Oui | Etat de la session |
| POST | /api/v1/solo/image-rebuild/sessions/:id/placements | Oui | Placer une brique |
| POST | /api/v1/solo/image-rebuild/sessions/:id/abandon | Oui | Abandonner |
| GET | /api/v1/solo/image-rebuild/sessions/:id/result | Oui | Resultat final |
| POST | /api/v1/solo/line-breaker/sessions | Oui | Creer partie lignes |
| GET | /api/v1/solo/line-breaker/sessions/:id | Oui | Etat de la session |
| POST | /api/v1/solo/line-breaker/sessions/:id/placements | Oui | Placer une brique |
| POST | /api/v1/solo/line-breaker/sessions/:id/abandon | Oui | Abandonner |
| GET | /api/v1/solo/line-breaker/sessions/:id/result | Oui | Resultat final |

### 4. Protocole WebSocket  - Messages

**Client → Serveur** : `session.authenticate`, `session.resume`, `room.create`, `room.join`, `room.leave`, `room.set_ready`, `room.start`, `chat.send`, `game.place_brick`, `game.request_state`, `ping`

**Serveur → Client** : `session.authenticated`, `session.resumed`, `room.created`, `room.joined`, `room.state`, `room.player_joined`, `room.player_left`, `room.player_ready_changed`, `room.chat_message`, `game.started`, `game.state`, `game.turn_started`, `game.turn_resolved`, `game.action_rejected`, `game.player_disconnected`, `game.player_reconnected`, `game.finished`, `error`, `pong`

### 5. Versions des dependances

| Composant | Version |
|---|---|
| Node.js (runtime) | ES Modules |
| TypeScript | 5.9.2 |
| Fastify | 5.6.1 |
| React | 19.1.1 |
| Vite | 7.1.3 |
| Mongoose | 8.18.0 |
| ws (WebSocket) | 8.18.3 |
| jose (JWT) | 6.1.0 |
| zod (validation) | 4.1.5 |
| Vitest (tests) | 3.2.4 |
| MongoDB Memory Server | 10.2.0 |
| PHP | 8.x |
| MariaDB | 10.11.13 |

### 6. Tests

265 tests automatises repartis en 37 fichiers :
- 139 tests unitaires (moteur, scoring, fidelite, contrats)
- 74 tests d'integration (sessions, duplicate, redemption, MongoDB, JWT)
- 52 tests de validation (securite PHP, structure, schema BDD)

Cahier de tests complet : `cahier-de-tests.md`
