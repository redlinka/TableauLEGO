# Games Platform Workspace

This workspace contains the new TableauLEGO game platform:

- `apps/game-server`: Fastify + WebSocket + Mongoose backend
- `apps/game-web`: React + TypeScript frontend
- `packages/game-contracts`: shared HTTP, WebSocket and domain contracts
- Documentation : voir les fichiers `documentation-*.md` et `cahier-de-tests.md` a la racine du projet

## Install

```bash
cd games_platform
npm install
```

## Local MongoDB

Start MongoDB with Docker:

```bash
npm run mongo:up
```

Stop it:

```bash
npm run mongo:down
```

Default connection:

- `mongodb://127.0.0.1:27017/tableaulego_games`

## Run

Backend:

```bash
npm run dev:server
```

Frontend:

```bash
npm run dev:web
```

Default URLs:

- backend: `http://127.0.0.1:3001`
- frontend: `http://127.0.0.1:5173`
- WebSocket: `ws://127.0.0.1:3001/ws`

## Build and tests

```bash
npm run build
npm test
```

Backend coverage currently includes:

- board engine and deterministic sequence unit tests
- `image_rebuild` and `line_breaker` scoring tests
- solo integration tests for both games
- duplicate room, duplicate gameplay, chat and reconnect tests
- loyalty formula tests
- history / loyalty route integration tests
- PHP signed-auth exchange integration test

## Demo flow

For a stable demo:

1. Start Mongo with `npm run mongo:up`
2. Start the backend with `npm run dev:server`
3. Start the frontend with `npm run dev:web`
4. Create a guest from the home screen
5. Play solo `image_rebuild`
6. Play solo `line_breaker`
7. Open a second browser tab and create / join a duplicate room
8. Start the room, play both sides, then inspect:
   - final score
   - history list
   - history detail
   - loyalty balance and ledger
9. To demonstrate reconnect:
   - join a duplicate room
   - close one tab socket or refresh the page
   - let the frontend resume automatically using the stored resume token

## Implemented scope

The workspace now supports:

- two games: `image_rebuild` and `line_breaker`
- solo mode
- duplicate 2-player mode with a shared deterministic sequence
- authoritative backend validation and scoring
- WebSocket lobby, ready flow, room start, chat and turn synchronization
- disconnect grace window and resume
- Mongo persistence for sessions, events, chat, history and loyalty
- puzzle loading from `image_puzzle_maker/output`
- minimal React UI with duplicate reconnect feedback, history and loyalty panels

## Puzzle integration

The backend loads puzzles from:

- `../image_puzzle_maker/output/manifest.json`
- `../image_puzzle_maker/output/json/*.json`

Puzzle previews are served from:

- `/assets/puzzle-previews/<file>`

## HTTP APIs

Available routes:

- `GET /health`
- `POST /api/v1/auth/guest`
- `POST /api/v1/auth/php/exchange`
- `GET /api/v1/bootstrap`
- `GET /api/v1/puzzles`
- `GET /api/v1/puzzles/:id`
- `GET /api/v1/history`
- `GET /api/v1/history/:historyId`
- `GET /api/v1/loyalty/balance`
- `GET /api/v1/loyalty/ledger`
- `POST /api/v1/solo/image-rebuild/sessions`
- `GET /api/v1/solo/image-rebuild/sessions/:sessionId`
- `POST /api/v1/solo/image-rebuild/sessions/:sessionId/placements`
- `POST /api/v1/solo/image-rebuild/sessions/:sessionId/abandon`
- `GET /api/v1/solo/image-rebuild/sessions/:sessionId/result`
- `POST /api/v1/solo/line-breaker/sessions`
- `GET /api/v1/solo/line-breaker/sessions/:sessionId`
- `POST /api/v1/solo/line-breaker/sessions/:sessionId/placements`
- `POST /api/v1/solo/line-breaker/sessions/:sessionId/abandon`
- `GET /api/v1/solo/line-breaker/sessions/:sessionId/result`

## WebSocket duplicate flow

Client -> server:

- `session.authenticate`
- `session.resume`
- `room.create`
- `room.join`
- `room.leave`
- `room.set_ready`
- `room.start`
- `chat.send`
- `game.place_brick`
- `game.request_state`
- `ping`

Server -> client:

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
- `game.turn_started`
- `game.turn_resolved`
- `game.action_rejected`
- `game.player_disconnected`
- `game.player_reconnected`
- `game.finished`
- `error`
- `pong`

Duplicate synchronization rules:

- both players share the same seed and the same brick offer sequence
- each player keeps an independent board
- each turn uses a common timer
- the turn resolves when both players have locked a move, or when the timer expires
- on timeout, the server auto-places when possible, otherwise the player is marked with no move
- after 60 seconds without resume, a disconnected player loses by forfeit

## Loyalty formula

Formula version: `loyalty_v1`

Principles:

- every completed game grants points
- better performance grants more points
- in duplicate: `win > draw > loss > forfeit`
- Node stores technical identifiers only

Solo base:

- participation: `10`
- performance: `min(30, floor(score / 80))`
- game bonus:
  - `image_rebuild`: based on matched cells and accuracy, capped at `20`
  - `line_breaker`: based on cleared lines and combo, capped at `20`
- abandoned adjustment: `-4`
- minimum floor: `6`

Duplicate base:

- participation: `12`
- performance: `min(20, floor(score / 100))`
- outcome bonus:
  - win: `18`
  - draw: `12`
  - loss: `8`
  - forfeit: `2`
- game bonus capped at `10`
- minimum floor:
  - normal duplicate finish: `8`
  - forfeit: `2`

`loyalty_ledger` stores at least:

- `playerId`
- `sessionId`
- `entryType`
- `pointsDelta`
- `balanceAfter`
- `reason`
- `gameType`
- `mode`
- `outcome`
- `score`
- `createdAt`
- `metadata`

## History

History entries expose:

- game type
- mode
- outcome
- final score
- reward points
- finish reason
- duration
- game-specific metrics in metadata

The frontend displays:

- recent sessions
- history detail
- loyalty balance
- loyalty ledger

## PHP signed integration

The backend already supports a future PHP bridge:

- signed JWT received from PHP
- verification via `PHP_GAME_TOKEN_SECRET`
- pseudonymized `playerId`
- optional pseudonymized `technicalLoyaltyId`
- no personal data stored in Node

Voir : `documentation-php-bridge.md` a la racine du projet.

## Environment

Backend environment variables are documented in:

- `apps/game-server/.env.example`

Most important for local work:

- `MONGODB_URI`
- `MONGODB_DB_NAME`
- `GAME_TOKEN_SECRET`
- `PHP_GAME_TOKEN_SECRET`
- `TOKEN_ISSUER`
- `TOKEN_AUDIENCE`
- `PHP_TOKEN_ISSUER`
- `PHP_TOKEN_AUDIENCE`
- `PUZZLE_OUTPUT_DIR`
- `SOLO_TURN_LIMIT_MS`
- `DUPLICATE_TURN_LIMIT_MS`
- `DUPLICATE_RECONNECT_GRACE_MS`
- `DUPLICATE_CHAT_MAX_LENGTH`
- `IMAGE_REBUILD_MAX_SEQUENCE_LENGTH`
- `LINE_BREAKER_MAX_SEQUENCE_LENGTH`
