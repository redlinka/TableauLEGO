# Cadrage Phase 2 - Plateforme de jeux

Date: 2026-03-12

## 1. Etat des lieux du repo

### Réutilisable

- Le site PHP existant reste la porte d'entrée utilisateur, avec session PHP et base MariaDB/MySQL pour le compte, le panier, les commandes et l'historique e-commerce.
- La connexion PHP repose déjà sur une session serveur et un `userId` en session après vérification du lien de connexion.
- Le pipeline `image_puzzle_maker/` est maintenant la source de vérité des puzzles 10x10 pour le jeu de reproduction d'image.
- Le Java/C historique contient des concepts utiles côté métier:
  - transformations d'image,
  - vocabulaire "brick",
  - catalogue de briques,
  - logique d'optimisation e-commerce,
  - intégration stock/catalogue.

### Non réutilisé comme runtime pour les jeux

- Le moteur PHP + Java + C historique ne convient pas comme socle de jeu temps réel autoritaire.
- La base SQL actuelle reste dédiée au e-commerce; elle ne doit pas devenir la persistance temps réel du mode duplicate.
- Le frontend historique Bootstrap/PHP n'est pas adapté à un gameplay temps réel riche; il sera conservé comme shell et point d'intégration.

### Manques actuels

- aucun backend Node.js + TypeScript de jeu
- aucun frontend React + TypeScript
- aucune couche WebSocket temps réel
- aucune persistance MongoDB
- aucun contrat réseau partagé
- aucun moteur de règles commun aux 2 jeux

## 2. Décisions de cadrage

- Backend de jeu: Node.js + TypeScript + Fastify
- Temps réel: WebSocket natif via `ws`
- Frontend jeu: React + TypeScript + Vite
- Persistance jeu: MongoDB via Mongoose
- Validation et score: exclusivement côté backend
- Intégration PHP: token technique signé par PHP, sans PII côté Node
- Puzzles image: chargés côté backend depuis `image_puzzle_maker/output/`, exposés au frontend via API

## 3. Architecture cible

```text
games_platform/
  apps/
    game-server/
      src/
        modules/
          auth/
          catalog/
          sessions/
          history/
          websocket/
        domain/
          common/
          image-rebuild/
          line-breaker/
        storage/
          mongo/
    game-web/
      src/
        pages/
        features/
          auth/
          lobby/
          gameplay/
          history/
        api/
        ws/
  packages/
    game-contracts/
      src/
```

### Backend

- `auth`: validation d'un jeton signé par PHP ou d'un jeton invité
- `catalog`: chargement du manifest puzzles et des JSON individuels
- `sessions`: création, reprise, transition lobby -> partie -> résultat
- `websocket`: connexions, rooms, diffusion d'état, présence, chat
- `history`: lecture de l'historique joueur
- `domain/common`: plateau, formes de briques, RNG déterministe, validation commune, score abstrait
- `domain/image-rebuild`: règles du jeu 1
- `domain/line-breaker`: règles du jeu 2

### Frontend

- SPA React servie séparément puis intégrée au site PHP
- consommation API HTTP pour bootstrap, puzzles, historique
- consommation WebSocket pour parties solo et duplicate
- rendu purement déclaratif, aucune logique métier critique

### Intégration PHP

- le site PHP reste responsable de l'authentification compte
- PHP expose un endpoint léger qui retourne un jeton jeu signé
- le frontend React récupère ce jeton puis dialogue avec le backend Node
- le build React pourra être servi soit par PHP en statique, soit par reverse proxy

### MongoDB

- Mongo stocke l'état vivant minimum nécessaire à la reprise
- Mongo stocke l'historique de partie, événements et chat
- aucun email, username ou autre PII n'est stocké côté Node

## 4. Modèles métier principaux

### Player

- `playerId`: identifiant technique pseudonymisé
- `authSource`: `php` | `guest`
- `publicAlias`: alias public pseudonyme, non personnel
- `createdAt`
- `lastSeenAt`
- `statsSummary`

### PuzzleTarget

- `id`
- `name`
- `gridWidth`
- `gridHeight`
- `palette`
- `paletteColors`
- `cells`
- `previewImage`
- `difficulty`

### BrickShape

- `shapeId`
- `label`
- `cells`: offsets relatifs
- `rotations`
- `weight`

### BrickOffer

- `offerId`
- `shapeId`
- `color`
- `issuedAtTurn`
- `sequenceIndex`

### BoardState

- `width`
- `height`
- `cells`
- `occupiedCount`
- `lastPlacement`

### GameSession

- `sessionId`
- `mode`: `solo` | `duplicate_2p`
- `gameType`: `image_rebuild` | `line_breaker`
- `status`: `waiting` | `active` | `completed` | `abandoned`
- `rulesetVersion`
- `seed`
- `roomCode`
- `hostPlayerId`
- `players`
- `sharedClock`
- `currentTurn`
- `sessionState`
- `result`
- `createdAt`
- `startedAt`
- `endedAt`

### PlayerSeat

- `seat`: `host` | `guest` | `solo`
- `playerId`
- `publicAlias`
- `connectionState`
- `ready`
- `resumeTokenHash`
- `board`
- `score`

### PlacementCommand

- `sessionId`
- `offerId`
- `anchorX`
- `anchorY`
- `rotation`

### ScoreResult

- `score`
- `breakdown`
- `rank`
- `accuracy`
- `linesCleared`
- `timeBonus`

### ChatMessage

- `messageId`
- `sessionId`
- `playerId`
- `publicAlias`
- `content`
- `createdAt`

### GameEvent

- `eventId`
- `sessionId`
- `seq`
- `type`
- `actorPlayerId`
- `payload`
- `createdAt`

### HistoryEntry

- `historyId`
- `playerId`
- `sessionId`
- `gameType`
- `mode`
- `outcome`
- `score`
- `opponentSummary`
- `startedAt`
- `endedAt`

## 5. Collections MongoDB

### `players`

- identité technique seulement
- index:
  - `{ playerId: 1 }` unique
  - `{ lastSeenAt: -1 }`

### `game_sessions`

- snapshot autoritaire de la session active ou terminée
- index:
  - `{ sessionId: 1 }` unique
  - `{ roomCode: 1 }` sparse unique
  - `{ "players.playerId": 1, createdAt: -1 }`
  - `{ status: 1, updatedAt: -1 }`

### `game_events`

- journal append-only des actions importantes
- index:
  - `{ sessionId: 1, seq: 1 }` unique
  - `{ actorPlayerId: 1, createdAt: -1 }`

### `chat_messages`

- chat de lobby et de partie duplicate
- index:
  - `{ sessionId: 1, createdAt: 1 }`

### `history_entries`

- projection légère pour l'écran historique
- index:
  - `{ playerId: 1, endedAt: -1 }`
  - `{ sessionId: 1 }`

### `loyalty_ledger`

- optionnel phase E uniquement
- index:
  - `{ playerId: 1, createdAt: -1 }`
  - `{ sessionId: 1 }`

## 6. Contrats réseau initiaux

### HTTP

- `POST /api/v1/auth/guest`
- `GET /api/v1/bootstrap`
- `GET /api/v1/puzzles`
- `GET /api/v1/puzzles/:id`
- `GET /api/v1/history`

### WebSocket

Format enveloppe:

```json
{
  "type": "room.create",
  "requestId": "req_123",
  "payload": {}
}
```

Réponse serveur:

```json
{
  "type": "room.state",
  "requestId": "req_123",
  "serverTime": 1760000000000,
  "payload": {}
}
```

#### Client -> serveur

- `session.authenticate`
- `session.resume`
- `solo.create`
- `room.create`
- `room.join`
- `room.leave`
- `room.set_ready`
- `room.start`
- `chat.send`
- `game.place_brick`
- `game.request_state`
- `ping`

#### Serveur -> client

- `session.authenticated`
- `session.resumed`
- `room.created`
- `room.joined`
- `room.state`
- `room.player_joined`
- `room.player_left`
- `room.player_ready_changed`
- `room.chat_message`
- `game.started`
- `game.state`
- `game.action_rejected`
- `game.player_disconnected`
- `game.player_reconnected`
- `game.finished`
- `error`
- `pong`

## 7. Intégration des JSON de image_puzzle_maker

### Décision

- Les JSON puzzles sont chargés côté backend uniquement.
- Le frontend ne lit jamais directement le système de fichiers.
- Le backend expose les métadonnées et, si nécessaire, le détail d'un puzzle via API.

### Chargement

- source: `image_puzzle_maker/output/manifest.json`
- pour chaque entrée: chargement du JSON référencé dans `image_puzzle_maker/output/json/`
- les previews sont servies en statique via le backend ou le reverse proxy

### Usage

- liste puzzles: `GET /api/v1/puzzles`
- détail puzzle: `GET /api/v1/puzzles/:id`
- création session `image_rebuild`: référence par `puzzleId`
- le backend garde le puzzle complet en mémoire et s'en sert pour validation et score

## 8. Règles et hypothèses prises

- grille commune initiale des deux jeux: 10x10
- séquence de briques déterministe générée côté serveur à partir d'un `seed`
- le duplicate 2 joueurs partage la même séquence et le même rythme serveur
- reconnexion avec jeton de reprise par siège joueur
- délai de grâce de déconnexion envisagé: 60 secondes
- alias public pseudonyme dérivé du compte PHP, pas de username réel en base Node
- `playerId` / `loyaltyId` recommandé: `base64url(HMAC_SHA256(secret, "php-user:" + user_id))`

### Score provisoire

- `image_rebuild`: score fondé sur placements valides, taux de correspondance final avec la cible, bonus de complétion, bonus temps
- `line_breaker`: score fondé sur cases posées, lignes monochromes effacées, combos, bonus de survie/fin de partie

## 9. Plan d'implémentation

### Phase A

- créer le workspace `games_platform/`
- mettre en place `game-server`, `game-web`, `game-contracts`
- brancher MongoDB via Mongoose
- créer les schémas, modèles et repositories de base
- poser dès cette phase les modèles d'historique et de fidélité
- figer un référentiel initial de briques partagé, rotations incluses, avec formes rectangulaires et lacunaires
- implémenter le loader de puzzles depuis `image_puzzle_maker/output/`

### Phase B

- implémenter le moteur commun de plateau
- implémenter formes de briques et validation de placement
- implémenter séquence déterministe
- implémenter logique solo du jeu `image_rebuild`

### Phase C

- implémenter logique solo du jeu `line_breaker`
- stabiliser le scoring et les états de fin de partie

### Phase D

- implémenter lobby duplicate
- implémenter WebSocket temps réel
- synchronisation 2 joueurs
- chat
- gestion déconnexion / reconnexion

### Phase E

- historique joueur
- résultats finaux
- éventuel ledger fidélité
- tests d'intégration, charge légère, robustesse

## 10. Références repo

- README projet: `README.md`
- session PHP et login: `Php/img2brick_final/verify_connexion.php`
- config PHP / MariaDB / CSRF / logs: `Php/img2brick_final/config/cnx.php`
- pipeline historique Java/C: `java/src/main/java/fr/uge/univ_eiffel/TileAndDraw.java`
- accès catalogue/stock MariaDB: `java/src/main/java/fr/uge/univ_eiffel/mediators/InventoryManager.java`
- schéma SQL historique: `DB/dump.sql`
- source de vérité puzzles: `image_puzzle_maker/output/manifest.json`
