# Déploiement — en local & sur LWS cPanel

Ce guide explique comment faire tourner le projet **en local** (dev) puis
**en production sur LWS cPanel**.

## Le projet = 2 services + 1 base de données

```
┌─────────────────────────┐      HTTP      ┌──────────────────────────┐
│  frontend/  (Laravel 12) │ ─────────────► │  backend/  (FastAPI, PY) │
│  • site web + admin + API│                │  • scrape anime-sama     │
│  • auth Sanctum          │                │  • search/episodes/...   │
│  • cache en base (MySQL) │                └──────────────────────────┘
└─────────────┬───────────┘
              ▼
        MySQL / SQLite
```

- **`frontend/`** (PHP) : le site, l'admin, l'API, le cache en base. → cPanel gère bien le PHP.
- **`backend/`** (Python/FastAPI) : le scraping d'anime-sama. → **Python sur mutualisé est délicat** (voir plus bas).
- Le frontend **met tout en cache en base** : une fois les données ingérées, il
  sert **sans backend live**. Le backend n'est nécessaire que pour **rafraîchir /
  ingérer** les données.

---

# Partie 1 — En local (développement)

Prérequis : **PHP 8.2+**, **Composer**, **Python 3.10+**, et (optionnel) **MySQL**.

## 1. Backend FastAPI (port 8001)

```bash
cd backend
python -m venv .venv
source .venv/bin/activate        # Windows : .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env             # optionnel : ANIMESAMA_TMDB_API_KEY pour les trailers
python run_dev.py                # démarre sur http://localhost:8001
```

## 2. Frontend Laravel (port 8000)

```bash
cd frontend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # base SQLite par défaut (dev)
php artisan migrate
php artisan serve --port=8000
```

Ouvre http://localhost:8000. Le frontend appelle le backend via
`ANIME_SAMA_BASE_URL` (défaut `http://localhost:8001`).

## 3. Remplir la base (pour ne pas dépendre du live)

```bash
php artisan anime:ingest          # catalogue complet (rapide)
php artisan anime:ingest --full   # + saisons/épisodes/liens (long)
php artisan user:make-admin toi@example.com   # accès à /admin
```

## 4. (Option) MySQL en local au lieu de SQLite

Dans `frontend/.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=animesama
DB_USERNAME=root
DB_PASSWORD=
```

Puis `php artisan migrate`.

---

# Partie 2 — Sur LWS cPanel

## Topologies possibles (choisis-en une)

| # | Site (Laravel) | Backend (FastAPI) | Base | Worker torrent |
|---|----------------|-------------------|------|----------------|
| **A** ✅ recommandé mutualisé | LWS cPanel | **ton PC** (ingest seulement) | MySQL LWS | ton PC |
| **B** | LWS cPanel | cPanel *Setup Python App* | MySQL LWS | ton PC / Real-Debrid |
| **C** | VPS | VPS | VPS | VPS |

> **Pourquoi A ?** Le mutualisé fait très bien tourner **le site PHP**, mais
> **mal le Python (FastAPI)** et **pas du tout aria2**. On garde donc sur cPanel
> ce qu'il gère bien (le site + MySQL), et on lance le **backend + worker depuis
> ton PC** quand tu veux rafraîchir/ajouter du contenu. Le site sert le reste du
> temps **depuis le cache en base**.

## Étape 1 — Base de données MySQL (cPanel)

1. cPanel → **MySQL Databases** : crée une base + un utilisateur, attribue-lui
   **tous les privilèges**.
2. Note : nom de base (`user_animesama`), utilisateur, mot de passe.

## Étape 2 — Déposer le site Laravel

1. **Version PHP** : cPanel → **MultiPHP Manager** → règle le domaine sur **PHP 8.2+**.
2. **Envoyer le code** : upload le dossier `frontend/` (via *Gestionnaire de
   fichiers* ou FTP) dans ton home, ex. `~/animesama` (**hors** `public_html`).
3. **Dépendances** :
   - Si cPanel a un **Terminal** : `cd ~/animesama && composer install --no-dev`.
   - Sinon : lance `composer install --no-dev` **en local** puis upload le dossier
     `vendor/` avec le reste.
4. **Faire pointer le domaine sur `public/`** (Laravel sert depuis `public/`) :
   - cPanel → **Domains** → *Document Root* du domaine → `~/animesama/public`.
   - (À défaut : copie le contenu de `public/` dans `public_html/` et adapte les
     chemins de `index.php` vers `~/animesama`.)

## Étape 3 — Configurer `.env` (production)

Crée `~/animesama/.env` :

```env
APP_NAME=Anime-Sama
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ton-domaine.fr
APP_KEY=                          # généré à l'étape suivante

DB_CONNECTION=mysql
DB_HOST=localhost                 # en local sur cPanel = localhost
DB_PORT=3306
DB_DATABASE=user_animesama
DB_USERNAME=user_dbuser
DB_PASSWORD=motdepasse

# Le backend n'est PAS hébergé sur cPanel (topologie A) : on sert depuis le
# cache en base. Laisse l'URL par défaut ; les requêtes non cachées échoueront
# proprement et serviront le contenu stocké.
ANIME_SAMA_STORE=true
ANIME_SAMA_LOCAL_SEARCH=true
```

Puis, via Terminal cPanel (ou en local avant upload) :

```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan user:make-admin toi@example.com
```

> Pas de Terminal cPanel ? Génère `APP_KEY` en local (`php artisan key:generate
> --show`) et colle-la dans `.env`, puis lance les migrations depuis ton PC
> **connecté à la base LWS distante** (voir Étape 5).

## Étape 4 — Cron cPanel (entretien + torrents)

cPanel → **Cron Jobs** → ajoute (chaque minute) :

```
* * * * * cd ~/animesama && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Cela fait tourner le rafraîchissement du cache et, si tu utilises **Real-Debrid**,
le traitement des torrents (`torrents:process`) — voir `torrents.md`.

## Étape 5 — Backend + ingestion + worker depuis ton PC

Le backend FastAPI et l'ingestion tournent **sur ton PC**, connectés à la **base
LWS distante** :

1. cPanel → **MySQL distant** : autorise l'**IP publique de ton PC**.
2. Sur ton PC, dans `frontend/.env`, pointe la base sur LWS :
   ```env
   DB_HOST=xxx.lws-hosting.com     # hôte MySQL distant fourni par LWS
   DB_DATABASE=user_animesama
   DB_USERNAME=user_dbuser
   DB_PASSWORD=motdepasse
   ```
3. Lance le backend (`python run_dev.py`) puis ingère :
   ```bash
   php artisan anime:ingest --full     # remplit la base LWS
   ```
4. Pour les **torrents gratuits (aria2)**, lance le worker depuis ton PC :
   voir **[`torrents.md`](torrents.md) → Option C**.

## HTTPS

LWS fournit un certificat SSL gratuit (Let's Encrypt) : cPanel → **SSL/TLS
Status** → *Run AutoSSL*. Mets `APP_URL=https://...`.

---

# Récapitulatif rapide

| Besoin | Local | LWS cPanel |
|--------|-------|------------|
| Site + admin | `php artisan serve` | domaine → `public/`, PHP 8.2+ |
| Base | SQLite (défaut) | MySQL cPanel |
| Backend scraping | `python run_dev.py` | **ton PC** (ingestion) |
| Rafraîchissement | scheduler local | cron cPanel (`schedule:run`) |
| Torrents gratuits | worker local | **ton PC** (voir torrents.md) |
| Torrents sans machine | — | **Real-Debrid** (cron cPanel) |

Pour toute la partie torrents (aria2 / Real-Debrid, quotas, admin), voir le
guide dédié : **[`torrents.md`](torrents.md)**.
