# Manuel d'installation - TableauLEGO

Guide pour faire fonctionner le projet complet sur une nouvelle machine.

---

## Prerequis a installer

| Logiciel | Version minimale | Lien |
|---|---|---|
| Node.js | 20+ | https://nodejs.org |
| npm | 10+ | (inclus avec Node.js) |
| PHP | 8.1+ | https://windows.php.net/download |
| MariaDB ou MySQL | 10.11+ | https://mariadb.org/download |
| Docker Desktop | (pour MongoDB) | https://www.docker.com/products/docker-desktop |
| Java JDK | 17+ | https://adoptium.net |
| Git | 2+ | https://git-scm.com |

### Extensions PHP requises

Verifier que ces extensions sont activees dans `php.ini` :

```
extension=pdo_mysql
extension=openssl
extension=mbstring
extension=curl
extension=fileinfo
extension=gd
```

---

## 1. Cloner le projet

```bash
git clone <url-du-repo> TableauLEGO
cd TableauLEGO
```

---

## 2. Base de donnees MariaDB (e-commerce)

### 2.1 Creer la base et importer le schema

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS img2brick_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p img2brick_db < DB/dump.sql
```

### 2.2 Creer un utilisateur dedie (optionnel)

```sql
CREATE USER 'tableaulego'@'localhost' IDENTIFIED BY 'motdepasse';
GRANT ALL PRIVILEGES ON img2brick_db.* TO 'tableaulego'@'localhost';
FLUSH PRIVILEGES;
```

---

## 3. Site e-commerce PHP

### 3.1 Creer le fichier `.env` PHP

Creer le fichier `Php/img2brick_final/config/.env` avec le contenu suivant :

```ini
; --- Base de donnees MariaDB ---
USER=tableaulego
PASS=motdepasse
DB=img2brick_db
HOST=127.0.0.1
PORT=3306

; --- Mode dev (desactive HTTPS, Turnstile captcha) ---
LOCAL_DEVELOPMENT=true

; --- Algorithme de hashage mot de passe ---
ALGO=2y

; --- SMTP pour les emails (inscription, 2FA, commandes) ---
; Remplacer par vos identifiants SMTP
SMTP_USER=votre.email@example.com
SMTP_PASS=votre_mot_de_passe_smtp

; --- Cloudflare Turnstile (captcha) ---
; En mode LOCAL_DEVELOPMENT=true, le captcha est desactive
; Pour la prod, obtenir des cles sur https://dash.cloudflare.com/turnstile
CLOUDFLARE_TURNSTILE_PUBLIC=0x0000000000000000000000
CLOUDFLARE_TURNSTILE_SECRET=0x0000000000000000000000

; --- Integration plateforme de jeux ---
GAMES_API_URL=http://127.0.0.1:3001
GAME_WEB_URL=http://127.0.0.1:5173
PHP_GAME_TOKEN_SECRET=une-cle-secrete-de-minimum-32-caracteres-ici
PHP_TOKEN_ISSUER=tableaulego-php
PHP_TOKEN_AUDIENCE=tableaulego-games

; --- Traduction DeepL (optionnel) ---
; DEEPL_AUTH_KEY=votre-cle-deepl
```

### 3.2 Creer les dossiers utilisateurs

```bash
mkdir -p Php/img2brick_final/users/imgs
mkdir -p Php/img2brick_final/users/tilings
```

### 3.3 Lancer le serveur PHP

```bash
cd Php/img2brick_final
php -S 127.0.0.1:8080
```

Le site est accessible sur `http://127.0.0.1:8080`.

---

## 4. MongoDB (base de donnees jeux)

### Option A : Docker (recommande)

```bash
cd games_platform
docker compose -f docker-compose.mongo.yml up -d
```

MongoDB est accessible sur `mongodb://127.0.0.1:27017/tableaulego_games`.

### Option B : MongoDB installe localement

S'assurer que `mongod` tourne sur le port 27017 par defaut.

---

## 5. Backend de jeu (Node.js)

### 5.1 Installer les dependances

```bash
cd games_platform
npm install
```

### 5.2 Compiler les contrats partages

```bash
npm run build --workspace=packages/game-contracts
```

### 5.3 Creer le fichier `.env` du backend

Creer le fichier `games_platform/apps/game-server/.env` :

```ini
NODE_ENV=development
HOST=127.0.0.1
PORT=3001
APP_VERSION=0.1.0
CORS_ORIGIN=http://127.0.0.1:5173

# MongoDB
MONGODB_URI=mongodb://127.0.0.1:27017/tableaulego_games
MONGODB_DB_NAME=tableaulego_games

# Secrets JWT
GAME_TOKEN_SECRET=change-me-for-development
PHP_GAME_TOKEN_SECRET=une-cle-secrete-de-minimum-32-caracteres-ici

# Issuers (doivent correspondre au .env PHP)
TOKEN_ISSUER=tableaulego-game-server
TOKEN_AUDIENCE=tableaulego-games
PHP_TOKEN_ISSUER=tableaulego-php
PHP_TOKEN_AUDIENCE=tableaulego-games

# Duree de vie token invites (7 jours en secondes)
GUEST_TOKEN_TTL_SECONDS=604800

# Previews puzzles
STATIC_PREVIEW_PREFIX=/assets/puzzle-previews/
WS_PATH=/ws

# Laisser vide pour dev local
PUBLIC_BASE_URL=
PUBLIC_WS_URL=
PUZZLE_OUTPUT_DIR=

# Timers de jeu (ms)
SOLO_TURN_LIMIT_MS=30000
DUPLICATE_TURN_LIMIT_MS=30000
DUPLICATE_RECONNECT_GRACE_MS=60000
DUPLICATE_CHAT_MAX_LENGTH=240

# Longueur max des sequences de briques
IMAGE_REBUILD_MAX_SEQUENCE_LENGTH=160
LINE_BREAKER_MAX_SEQUENCE_LENGTH=200

# URL du site PHP (pour la politique de fidelite)
PHP_API_URL=http://127.0.0.1:8080
```

**IMPORTANT** : la valeur de `PHP_GAME_TOKEN_SECRET` doit etre identique dans les deux `.env` (PHP et Node).

### 5.4 Lancer le backend

```bash
cd games_platform
npm run dev:server
```

Le backend est accessible sur `http://127.0.0.1:3001`.
Verifier : `http://127.0.0.1:3001/health` doit retourner `{"status":"ok",...}`.

---

## 6. Frontend de jeu (React)

### 6.1 Lancer le frontend

```bash
cd games_platform
npm run dev:web
```

Le frontend est accessible sur `http://127.0.0.1:5173`.

### 6.2 Variables d'environnement frontend (optionnel)

Si les URLs par defaut ne conviennent pas, creer `games_platform/apps/game-web/.env` :

```ini
VITE_API_BASE_URL=http://127.0.0.1:3001
VITE_DEBUG_UI=false
VITE_PHP_SHOP_URL=http://127.0.0.1:8080
```

---

## 7. Verifier que tout fonctionne

### 7.1 Tests automatises (265 tests)

```bash
cd games_platform/apps/game-server
npx vitest run
```

Resultat attendu : `37 passed / 265 tests / 0 failed`.

Les tests utilisent MongoDB Memory Server (pas besoin de MongoDB en cours).

### 7.2 Verification manuelle

| Etape | URL | Attendu |
|---|---|---|
| Health backend | http://127.0.0.1:3001/health | `{"status":"ok"}` |
| Bootstrap | http://127.0.0.1:3001/api/v1/bootstrap | JSON avec jeux, briques, fidelite |
| Site PHP | http://127.0.0.1:8080 | Page d'accueil boutique |
| Jeux React | http://127.0.0.1:5173 | Interface BrickMosaic |
| Politique fidelite | http://127.0.0.1:8080/api/loyalty_policy.php | JSON avec tiers et multiplicateurs |

### 7.3 Tester le flux complet

1. Ouvrir `http://127.0.0.1:5173`
2. Cliquer sur un jeu (Image Rebuild ou Line Breaker)
3. Lancer une partie solo - verifier que les briques se placent
4. Consulter l'historique et les points de fidelite
5. Ouvrir un 2e onglet, creer un salon duplicate, rejoindre avec le code
6. Verifier le chat et la synchronisation des tours

---

## 8. Ordre de lancement

Lancer les services dans cet ordre :

```
1. MariaDB          (doit etre demarre)
2. Docker MongoDB   docker compose -f games_platform/docker-compose.mongo.yml up -d
3. PHP              cd Php/img2brick_final && php -S 127.0.0.1:8080
4. Backend Node     cd games_platform && npm run dev:server
5. Frontend React   cd games_platform && npm run dev:web
```

---

## 9. Problemes courants

| Probleme | Cause probable | Solution |
|---|---|---|
| `ECONNREFUSED :27017` | MongoDB non demarre | `docker compose up -d` dans games_platform |
| `SQLSTATE connection refused` | MariaDB non demarre ou mauvais identifiants | Verifier MariaDB et le `.env` PHP |
| `PHP_GAME_TOKEN_SECRET must be at least 32 characters` | Secret trop court | Mettre une chaine de 32+ caracteres dans les deux `.env` |
| CORS bloque sur le frontend | CORS_ORIGIN ne correspond pas | Mettre `http://127.0.0.1:5173` dans le `.env` Node |
| Puzzles non charges | Puzzles pas generes | Verifier que `image_puzzle_maker/output/manifest.json` existe |
| Captcha echoue en local | Turnstile actif en dev | Mettre `LOCAL_DEVELOPMENT=true` dans le `.env` PHP |
| Emails non envoyes | SMTP non configure | Configurer `SMTP_USER` et `SMTP_PASS` dans le `.env` PHP |

---

## 10. Structure des fichiers `.env`

```
TableauLEGO/
├── Php/img2brick_final/config/.env    <-- a creer (section 3.1)
├── games_platform/
│   └── apps/game-server/.env          <-- a creer (section 5.3)
│   └── apps/game-web/.env             <-- optionnel (section 6.2)
```

Ces fichiers sont dans le `.gitignore` et ne sont jamais commites.
