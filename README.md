# Anime-Sama — FastAPI + Laravel + Sanctum

A two-service app for browsing and watching anime-sama:

- **`backend/`** — a **FastAPI** service that wraps the real
  [`anime-sama_api`](https://github.com/Sky-NiniKo/anime-sama_api) Python
  library and exposes it as JSON (search, catalogue, seasons, episodes/players,
  planning, trailers).
- **`frontend/`** — a **Laravel 12** app that adds **Sanctum** token auth,
  favorites and watch history, serves the web UI, and **proxies** catalogue
  requests to the backend (with a short-lived response cache).

The Laravel app does **not** re-implement the scraping: it forwards to the
Python service, so the actual `anime-sama_api` library is the single source of
truth.

```
Browser / SPA / mobile app
        │  Authorization: Bearer <token>  (Sanctum)
        ▼
┌───────────────────────────────────────────────┐        ┌──────────────────────────────┐
│  Laravel 12 (frontend/, :8000)                 │  HTTP  │  FastAPI (backend/, :8001)    │
│  • Sanctum auth (register/login/logout)        │ ─────► │  • wraps anime-sama_api       │
│  • App\Services\AnimeSamaClient (proxy) ───────┼──────► │  • search/catalogue/episodes  │
│  • favorites + watch history (SQLite/MySQL)    │        │  • planning, trailers (TMDB)  │
│  • short-lived response cache                  │        │  • auto-resolves live domain  │
└───────────────────────────────────────────────┘        └───────────────┬──────────────┘
                                                                          ▼
                                                                    anime-sama.*
```

## Quick start (two terminals)

### 1. Backend (FastAPI) — port 8001

Requires Python 3.10+. Using [`uv`](https://docs.astral.sh/uv/):

```bash
cd backend
uv venv --python 3.12 .venv
source .venv/bin/activate
uv pip install -r requirements.txt
cp .env.example .env            # optional; set ANIMESAMA_TMDB_API_KEY for trailers
uvicorn app.main:app --reload --port 8001    # or: python run_dev.py
```

On startup the backend auto-resolves the current anime-sama domain (the domain
rotates periodically), so it works even when the default in `.env` is stale.

### 2. Frontend (Laravel) — port 8000

```bash
cd frontend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite       # default DB is sqlite
php artisan migrate
php artisan serve --port=8000
```

Open http://localhost:8000. The frontend talks to the backend via
`ANIME_SAMA_BASE_URL` (defaults to `http://localhost:8001`).

## Trailers

anime-sama has **no** trailer data, so the hero background trailer is sourced
from **TMDB** (which returns official YouTube keys). It auto-plays muted behind
the hero, with a small speaker toggle to unmute — there is no "play trailer"
button. Set `ANIMESAMA_TMDB_API_KEY` in `backend/.env` (free key from
[themoviedb.org](https://www.themoviedb.org/settings/api)) to enable it; without
a key the UI keeps the animated poster background.

## Persistence & caching (MySQL)

To avoid hammering — or going down with — the upstream anime-sama site, the
Laravel app **persists every API response in the database** (`anime_caches`
table), including each episode's player/mirror ("vid") links. Requests are
served from the DB and only reach anime-sama when a row is missing or stale.

- **Per-type freshness windows** (`ANIME_SAMA_TTL_*`): episode/player links use
  a short TTL (they rotate), catalogue metadata a long one.
- **Stale-while-error**: if a refresh fails (anime-sama slow/blocked/down), the
  last stored payload is served instead of an error, so the site keeps working.
- **Stampede protection**: concurrent misses for the same key collapse into a
  single upstream call via a lock.

SQLite works out of the box; **MySQL is recommended in production** — set the
`DB_CONNECTION=mysql` block in `frontend/.env` and run `php artisan migrate`.

Toggle/tune via `frontend/.env`:

```env
ANIME_SAMA_STORE=true            # false = always go live (no DB cache)
ANIME_SAMA_TTL_SEARCH=21600      # 6h
ANIME_SAMA_TTL_CATALOGUE=43200   # 12h
ANIME_SAMA_TTL_EPISODES=3600     # 1h  (player links rotate)
ANIME_SAMA_TTL_NEW_EPISODES=900  # 15m
ANIME_SAMA_TTL_PLANNING=21600    # 6h
ANIME_SAMA_TTL_TRAILER=2592000   # 30d
```

Maintenance commands (schedule them so users never trigger a live fetch):

```bash
php artisan anime:cache-refresh "one piece" "jujutsu kaisen"  # warm feeds + searches
php artisan anime:cache-prune --days=30                        # drop long-unused rows
```

## Backend API (FastAPI, :8001)

| Method | Path                  | Description                                   |
|--------|-----------------------|-----------------------------------------------|
| GET    | `/health`             | Health + current site URL + version           |
| GET    | `/api/site`           | Current anime-sama base URL                    |
| POST   | `/api/site/resolve`   | Auto-detect the current anime-sama domain      |
| GET    | `/api/search?q=`      | Search catalogues                              |
| GET    | `/api/new-episodes`   | Latest episode releases (homepage)             |
| GET    | `/api/planning`       | Release planning (501 until implemented upstream) |
| GET    | `/api/catalogue?url=` | Catalogue detail (synopsis, seasons, ...)      |
| GET    | `/api/episodes?url=`  | Episodes + player links for a season URL       |
| GET    | `/api/trailer?title=` | Best YouTube trailer key (TMDB, optional)      |

Interactive docs at `/docs`. All `/api/*` routes require the `X-API-Key` header
when `ANIMESAMA_API_KEY` is set (leave empty for local dev).

## Frontend API (Laravel, :8000)

Base URL: `http://localhost:8000/api`

### Authentication (public)

| Method | Endpoint          | Body                                            |
|--------|-------------------|-------------------------------------------------|
| POST   | `/auth/register`  | `name, email, password, password_confirmation`  |
| POST   | `/auth/login`     | `email, password` → returns `{ token }`         |

Send the token as `Authorization: Bearer <token>` on protected routes.

### Catalogue browsing (public, proxied to the backend)

| Method | Endpoint               | Query                          |
|--------|------------------------|--------------------------------|
| GET    | `/anime/search`        | `q`                            |
| GET    | `/anime/new-episodes`  | –                              |
| GET    | `/anime/planning`      | –                              |
| GET    | `/anime/catalogue`     | `url`                          |
| GET    | `/anime/episodes`      | `url`, optional `prefer`       |
| GET    | `/anime/trailer`       | `title` (returns `youtube_key`)|
| GET    | `/anime/site`          | current anime-sama URL         |
| POST   | `/anime/site/resolve`  | auto-detect the current domain |

### Protected (require `auth:sanctum`)

| Method | Endpoint            | Description                       |
|--------|---------------------|-----------------------------------|
| GET    | `/auth/me`          | Current user                      |
| POST   | `/auth/logout`      | Revoke current token              |
| POST   | `/auth/logout-all`  | Revoke all tokens                 |
| GET    | `/favorites`        | List favorites                    |
| POST   | `/favorites`        | Add favorite                      |
| DELETE | `/favorites/{id}`   | Remove favorite                   |
| GET    | `/history`          | List watch history                |
| POST   | `/history`          | Upsert watch progress             |
| DELETE | `/history/{id}`     | Remove history entry              |

### Example flow

```bash
# Register
curl -s -X POST http://localhost:8000/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Jane","email":"jane@example.com","password":"password123","password_confirmation":"password123"}'

# Search (public, proxied to the backend)
curl -s 'http://localhost:8000/api/anime/search?q=naruto'

# Add a favorite (authenticated)
curl -s -X POST http://localhost:8000/api/favorites \
  -H "Authorization: Bearer <TOKEN>" -H 'Content-Type: application/json' \
  -d '{"catalogue_url":"https://anime-sama.fr/catalogue/naruto/","name":"Naruto"}'
```

## Testing

```bash
cd frontend && php artisan test
```

The Laravel tests fake the backend with `Http::fake`, so they need no network
access and no running backend.

## Notes & legal

- The anime-sama domain changes periodically. The backend auto-resolves it on
  startup; you can also call `POST /api/anime/site/resolve` at runtime.
- This project only reads publicly available data via the upstream library and
  is intended for **personal/educational use**. Respect the terms of service of
  the sites involved.
