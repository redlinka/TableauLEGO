# TableauLEGO

Plateforme web permettant de generer et commander des tableaux de briques LEGO a partir d'images, integrant un systeme de gamification et de fidelite.

## Equipe

- **Product Owner** : Olivier CHAMPALLE
- **Scrum Master** : Adam KADI
- **Developpeurs** : Adam KADI, Theo JULLIEN, Matheo LARIVIERE

Projet realise du 20 fevrier au 9 avril en methodologie Agile-SCRUM (8 sprints).

## Architecture du projet

```
TableauLEGO/
├── Php/img2brick_final/         # Site e-commerce PHP (boutique, panier, commandes, comptes)
├── games_platform/              # Plateforme de jeux (Node.js + React + MongoDB)
│   ├── apps/game-server/        # Backend Fastify + WebSocket + Mongoose
│   ├── apps/game-web/           # Frontend React + TypeScript
│   └── packages/game-contracts/ # Types partages entre backend et frontend
├── image_puzzle_maker/          # Pipeline de generation de puzzles (images → grilles LEGO)
├── C/                           # Algorithme de tiling quadtree (C)
├── java/                        # Orchestration pipeline Java (tiling, inventaire, commandes)
└── DB/                          # Schema et migrations base de donnees MariaDB
```

## Technologies

| Couche | Technologies |
|---|---|
| Frontend jeux | React 19, TypeScript 5.9, Vite 7 |
| Backend jeux | Fastify 5.6, WebSocket (ws 8.18), Mongoose 8.18 |
| Base de donnees jeux | MongoDB |
| Site e-commerce | PHP 8, MariaDB 10.11 |
| Authentification | JWT (jose 6.1), HMAC-SHA256 |
| Algorithmes | C (quadtree tiling), Java (orchestration pipeline) |
| Tests | Vitest 3.2, MongoDB Memory Server (265 tests) |

## Documentation

| Document | Description |
|---|---|
| [Dossier de conception](dossier-de-conception.md) | Conception technique detaillee (MCD, MLD, MPD, architecture, sprints) |
| [Consignes du jeu](Consignesjeu.md) | Cahier des charges complet du projet |
| [Documentation React](documentation-react.md) | Architecture, jeux, controles, API, WebSocket du frontend |
| [Cahier de tests](cahier-de-tests.md) | 265 tests  - unitaires, integration, validation (Vitest) |
| [Pont PHP / Node](documentation-php-bridge.md) | Contrat JWT pour l'authentification PHP ↔ Node.js |
| [Cadrage plateforme de jeux](documentation-cadrage.md) | Architecture et decisions techniques Phase 2 |
| [Base de donnees](DB/README.md) | Schema MariaDB, tables, contraintes, vues |
| [Algorithme C](C/README.md) | Tiling quadtree, gestion de stock, pavage |
| [Image Puzzle Maker](image_puzzle_maker/README.md) | Pipeline de conversion images → puzzles LEGO |

## Jeux implementes

- **Reproduction d'image**  - Reproduire une image pixellisee en placant des briques LEGO sur un tableau 10x10
- **Casse-briques de lignes**  - Placer des briques pour former des lignes monochromes (inspire de Tetris, avec briques lacunaires)

Chaque jeu est jouable en **solo** ou en **mode duplicate** (2 joueurs en temps reel via WebSocket).

### Controles

- **Souris / Tactile** : clic pour placer, survol pour previsualiser, boutons de rotation
- **Clavier** : fleches pour deplacer le curseur, R pour tourner, Entree pour confirmer

## Systeme de fidelite

- Points attribues apres chaque partie (min. 2-10 pts garantis)
- Multiplicateurs temporels (happy hour soir x1.5, weekend x2, midi x1.25)
- Expiration des points (30 jours par defaut)
- Consommation FIFO par date d'echeance
- Echange contre des reductions sur la boutique (200 pts → 5%, 500 pts → 10%, 1000 pts → 20%)
- Politique definie cote PHP, consommee par le backend Node.js

## Lancer le projet

```bash
# Backend de jeu
cd games_platform && npm install && npm run dev --workspace=apps/game-server

# Frontend de jeu
cd games_platform && npm run dev --workspace=apps/game-web

# Tests (265 tests)
cd games_platform/apps/game-server && npx vitest run
```

## Sprints

| Sprint | Dates | Objectif | Jalon |
|---|---|---|---|
| 0 | 20-23 fev | Setup environnement | Environnement pret |
| 1 | 24 fev - 2 mar | Jeu solo fonctionnel | Jeu jouable |
| 2 | 3-9 mar | Backend + BDD | Backend connecte |
| 3 | 10-16 mar | Mode multijoueur | Duplicate OK |
| 4 | 17-23 mar | Systeme de fidelite | Fidelite OK |
| 5 | 24-30 mar | Application mobile | Mobile OK |
| 6 | 31 mar - 6 avr | Qualite + communication | Version finale |
| 7 | 7-9 avr | Livraison | Soutenance |
