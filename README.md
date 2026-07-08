# Anime-Sama — Laravel + Sanctum

A single **Laravel 12** application that scrapes the public
[anime-sama](https://anime-sama.fr) website **natively in PHP** (a port of the
[`anime-sama_api`](https://github.com/Sky-NiniKo/anime-sama_api) Python library)
and exposes it as a JSON API with **Sanctum** token authentication, favorites
and watch history.

> There is no separate backend service anymore: the scraping logic runs inside
> Laravel (`app/Services/AnimeSama/`), so you only run one app.

```
Browser / SPA / mobile app
        │  Authorization: Bearer <token>  (Sanctum)
        ▼
┌───────────────────────────────────────────────┐
│  Laravel 12 (frontend/, :8000)                 │
│  • Sanctum auth (register/login/logout)        │
│  • App\Services\AnimeSama\Scraper  ────────────┼──► scrapes anime-sama
│  • favorites + watch history (SQLite/MySQL)    │
│  • short-lived response cache                  │
└───────────────────────────────────────────────┘
```

## The scraper (`app/Services/AnimeSama/`)

A faithful PHP port of the Python library:

| File          | Ports (Python)                     | Responsibility                                   |
|---------------|------------------------------------|--------------------------------------------------|
| `Lang.php`    | `langs.py`                         | language id ⇄ language maps, allowed categories  |
| `Players.php` | `Players`                          | mirror URLs per episode (swap + vidmoly fix)     |
| `Episode.php` | `Episode` / `Languages`            | languages, best-player resolution, serialisation |
| `Scraper.php` | `top_level.py` / `catalogue.py` / `season.py` | search, catalogue, seasons, episodes, site resolve |

## Setup

```bash
cd frontend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite       # default DB is sqlite
php artisan migrate
php artisan serve --port=8000
```

The app runs on http://localhost:8000 (health at `/up`).

Relevant `.env` settings (all optional, sensible defaults provided):

```env
ANIME_SAMA_SITE_URL=https://anime-sama.fr/      # anime-sama base URL
ANIME_SAMA_PREFER_LANGUAGES=VF,VOSTFR           # "best player" order
ANIME_SAMA_TIMEOUT=20                            # scraping HTTP timeout (s)
ANIME_SAMA_CACHE_TTL=300                         # response cache TTL (s), 0 = off

# Optional: real video trailers in the hero background (free key).
# https://www.themoviedb.org/settings/api — leave empty to use animated posters.
TMDB_API_KEY=
TMDB_LANGUAGE=fr-FR
```

> **Trailers en fond** : sans `TMDB_API_KEY`, le héros affiche les affiches
> animées (effet Ken Burns). Avec une clé TMDB, une vraie bande-annonce vidéo
> (YouTube, muette + autoplay) se lance en fond, avec un bouton son.

## API

Base URL: `http://localhost:8000/api`

### Authentication (public)

| Method | Endpoint          | Body                                            |
|--------|-------------------|-------------------------------------------------|
| POST   | `/auth/register`  | `name, email, password, password_confirmation`  |
| POST   | `/auth/login`     | `email, password` → returns `{ token }`         |

Send the token as `Authorization: Bearer <token>` on protected routes.

### Catalogue browsing (public, scraped from anime-sama)

| Method | Endpoint               | Query                          |
|--------|------------------------|--------------------------------|
| GET    | `/anime/search`        | `q`                            |
| GET    | `/anime/new-episodes`  | –                              |
| GET    | `/anime/catalogue`     | `url`                          |
| GET    | `/anime/episodes`      | `url`, optional `prefer`       |
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

# Search (public)
curl -s 'http://localhost:8000/api/anime/search?q=naruto'

# Episodes + player links for a season
curl -s 'http://localhost:8000/api/anime/episodes?url=https://anime-sama.fr/catalogue/naruto/saison1/'

# Add a favorite (authenticated)
curl -s -X POST http://localhost:8000/api/favorites \
  -H "Authorization: Bearer <TOKEN>" -H 'Content-Type: application/json' \
  -d '{"catalogue_url":"https://anime-sama.fr/catalogue/naruto/","name":"Naruto"}'
```

## Testing

```bash
cd frontend && php artisan test
```

Tests fake anime-sama's HTML with `Http::fake`, so they need no network access.

## Notes & legal

- The anime-sama domain changes periodically. Set `ANIME_SAMA_SITE_URL` or call
  `POST /api/anime/site/resolve` to auto-detect the current domain.
- This project only reads publicly available data and is intended for
  **personal/educational use**. Respect the terms of service of the sites
  involved.
