# Documentation  - Application de jeu React (Frontend)

## Vue d'ensemble

L'application de jeu React est le frontend web de la plateforme de fidélisation TableauLEGO. Elle permet aux joueurs de participer à deux types de jeux sur le thème des briques LEGO, en mode solo ou en mode duplicate (2 joueurs), afin de gagner des points de fidélité échangeables contre des réductions sur la boutique.

**Technologies utilisées :**
- React 19 avec TypeScript
- WebSocket natif pour le temps réel (mode duplicate)
- Vite comme bundler de développement
- CSS responsive avec media queries

## Architecture du code

```
apps/game-web/src/
├── App.tsx                 # Composant principal (logique de jeu, rendu, state)
├── main.tsx                # Point d'entrée React
├── api/
│   ├── client.ts           # Client HTTP générique (fetch wrapper)
│   └── gameApi.ts          # Fonctions d'appel API typées
├── ws/
│   └── websocketClient.ts  # Client WebSocket (connexion, envoi, écoute)
├── colorUtils.ts           # Correspondance couleurs → palette LEGO
├── gameContent.ts          # Textes descriptifs des jeux, règles, explication des points
└── styles.css              # Feuille de styles responsive
```

### Composant principal (`App.tsx`)

Le fichier `App.tsx` contient l'ensemble de la logique applicative dans un composant fonctionnel unique. Ce choix permet de centraliser l'état du jeu et simplifie la gestion des interactions WebSocket.

**États principaux :**
- `selectedGameType` / `selectedMode` : type de jeu et mode en cours
- `imageState` / `lineState` : état de la session solo (image rebuild / line breaker)
- `duplicateState` : état de la session duplicate (salon, joueurs, chat)
- `guestState` : authentification (token invité ou token PHP)
- `hoverCell` / `selectedRotation` : position du curseur et rotation de la brique

## Jeux implémentés

### Jeu 1  - Reproduction d'image (`ImageRebuild`)

Le joueur reçoit une brique à chaque tour et doit la placer sur un tableau 10×10 pour reproduire le plus fidèlement une image cible pixellisée en palette LEGO (12 couleurs).

**Score :** basé sur l'exactitude (ratio de cellules correspondant à la cible) et la couverture (pourcentage du tableau rempli).

### Jeu 2  - Casse-briques de lignes (`LineBreaker`)

Inspiré de Tetris. Le joueur place des briques pour constituer des lignes monochromes (horizontales ou verticales). Les lignes complètes sont effacées et rapportent des points. Des briques lacunaires (L, T, zigzag, U, cadre) sont incluses.

**Score :** basé sur le nombre de lignes effacées et les combos (lignes multiples simultanées).

### Fin de partie

- **Solo :** la partie se termine quand le tableau est plein (aucun placement possible), la séquence de briques est épuisée, ou le joueur abandonne.
- **Duplicate :** la partie se termine quand les deux joueurs ont fini ou quand un joueur se déconnecte au-delà du délai de grâce (60 secondes).

## Mode Duplicate (2 joueurs)

Le mode duplicate utilise un protocole WebSocket pour la synchronisation en temps réel.

### Flux de connexion

1. Le joueur 1 (hôte) crée un salon → reçoit un code de partie
2. Le joueur 2 rejoint le salon en saisissant le code
3. Les deux joueurs peuvent discuter par chat textuel
4. Le joueur 1 lance la partie quand les deux sont prêts
5. Pendant la partie, la même séquence de briques est distribuée aux deux joueurs
6. Chaque joueur place ses briques sur son propre tableau
7. Les deux joueurs voient leur tableau et celui de l'adversaire

### Messages WebSocket

| Message client | Message serveur | Description |
|---|---|---|
| `session.authenticate` | `session.authenticated` | Authentification |
| `room.create` | `room.state` | Création de salon |
| `room.join` | `room.state` | Rejoindre un salon |
| `room.set_ready` | `room.player_ready_changed` | Signaler prêt |
| `room.start` | `game.turn_started` | Lancer la partie |
| `game.place_brick` | `game.turn_resolved` | Placer une brique |
| `chat.send` | `room.chat_message` | Envoyer un message |

## Contrôles

### Souris / Tactile
- Clic sur une cellule du tableau pour placer la brique
- Survol pour pré-visualiser le placement (fantôme)
- Boutons de rotation pour tourner la brique

### Clavier (desktop)
- **Flèches directionnelles** : déplacer le curseur sur le tableau
- **R** : tourner la brique (rotation suivante)
- **Entrée / Espace** : confirmer le placement à la position du curseur

## Points de fidélité

Chaque partie terminée rapporte des points de fidélité, attribués selon :
- **Participation** : points de base (10 solo, 12 duplicate)
- **Performance** : basée sur le score obtenu
- **Bonus de jeu** : fidélité de reproduction (image) ou lignes effacées (line breaker)
- **Issue (duplicate)** : victoire (18 pts), égalité (12 pts), défaite (8 pts)
- **Multiplicateur temporel** : bonus à certaines heures/jours (défini par la politique PHP)

Les points ont une **date d'expiration** (par défaut 30 jours). Lors de l'échange contre une réduction, les points expirant le plus tôt sont consommés en priorité (FIFO).

### Paliers de réduction

| Points requis | Réduction |
|---|---|
| 200 | 5 % |
| 500 | 10 % |
| 1 000 | 20 % |

## Authentification

Deux modes d'authentification sont supportés :

1. **Invité** : un token temporaire est généré automatiquement (`POST /api/v1/auth/guest`). Les points sont conservés mais ne sont pas rattachés à un compte boutique. En fin de partie, une bannière invite le joueur à créer un compte.

2. **Utilisateur PHP** : le site boutique génère un JWT signé, transmis via un fragment d'URL (`#phpToken=...`). Le frontend l'échange contre un token Node via `POST /api/v1/auth/php/exchange`. Les points sont rattachés au compte boutique de manière pseudonymisée.

## Communication avec le backend

### API REST (`api/gameApi.ts`)

| Fonction | Endpoint | Description |
|---|---|---|
| `authenticateGuest()` | `POST /auth/guest` | Créer un joueur invité |
| `exchangePhpToken()` | `POST /auth/php/exchange` | Échanger un JWT PHP |
| `fetchBootstrap()` | `GET /bootstrap` | Configuration initiale |
| `fetchPuzzles()` | `GET /puzzles` | Liste des puzzles |
| `createSoloImageRebuildSession()` | `POST /solo/image-rebuild/sessions` | Nouvelle partie image |
| `createSoloLineBreakerSession()` | `POST /solo/line-breaker/sessions` | Nouvelle partie lignes |
| `submitSolo*Placement()` | `POST /solo/*/sessions/:id/placements` | Placer une brique |
| `fetchHistory()` | `GET /history` | Historique des parties |
| `fetchLoyaltyBalance()` | `GET /loyalty/balance` | Solde de points |

### WebSocket (`ws/websocketClient.ts`)

Client WebSocket natif avec gestion de reconnexion et token de reprise (`resumeToken`) persisté en `localStorage`.

## Design responsive

L'interface s'adapte à toutes les tailles d'écran :

- **Desktop (> 1180px)** : disposition en colonnes multiples, tableau de jeu large
- **Tablette (840px  - 1180px)** : passage en colonne unique
- **Mobile (< 840px)** : interface empilée, taille de cellules réduite
- **Petit mobile (< 520px)** : ajustements supplémentaires

Le tableau de jeu utilise `CSS Grid` avec `gridTemplateColumns: repeat(width, 1fr)` pour s'adapter dynamiquement à la taille de la grille. Les cellules sont carrées (`aspect-ratio: 1/1`).

## Variables d'environnement (Vite)

| Variable | Description | Défaut |
|---|---|---|
| `VITE_API_BASE_URL` | URL de base du backend Node.js | `http://127.0.0.1:3001` |
| `VITE_DEBUG_UI` | Afficher le panneau debug | `false` |
| `VITE_PHP_SHOP_URL` | URL de la boutique PHP | `http://127.0.0.1:8080` |

## Lancer le projet en développement

```bash
cd games_platform
npm install
npm run dev --workspace=apps/game-web
```

Le serveur de développement Vite démarre sur `http://127.0.0.1:5173`.

## Contrat de types partagés

Les types TypeScript sont définis dans le package `@games-platform/game-contracts` (`packages/game-contracts/`). Ce package est partagé entre le frontend et le backend pour garantir la cohérence des structures de données (enums, interfaces de session, types de briques, réponses API).
