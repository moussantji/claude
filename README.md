# Anime-Sama — FastAPI backend + Laravel/Sanctum frontend

This project combines two services:

| Service    | Stack                     | Role                                                                 |
|------------|---------------------------|----------------------------------------------------------------------|
| `backend/` | Python + FastAPI          | REST wrapper around [`anime-sama_api`](https://github.com/Sky-NiniKo/anime-sama_api) (scrapes anime-sama) |
| `frontend/`| PHP + Laravel 12 + Sanctum| Authenticated API gateway/app layer that consumes the backend and adds users, favorites and watch history |

```
Browser / SPA / mobile app
          │  Bearer token (Sanctum)
          ▼
┌─────────────────────┐        ┌──────────────────────────┐
│  Laravel + Sanctum   │  HTTP  │  FastAPI backend          │
│  (frontend/, :8000)  ├───────►│  (backend/, :8001)        │
│  auth, favorites,    │  X-API │  wraps anime-sama_api     │
│  history, caching    │  -Key  │  → scrapes anime-sama     │
└─────────────────────┘        └──────────────────────────┘
```

The Laravel layer holds user accounts (Sanctum personal access tokens),
favorites and watch history, and **proxies** all catalogue/episode requests to
the Python backend (with short-lived response caching). The Python backend does
the actual scraping via the upstream library.

---

## Setup

Run the two services in **two separate terminals**.

### 1. Backend (FastAPI) — terminal 1

```bash
cd backend
python3 -m venv .venv
source .venv/bin/activate          # Windows: .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
uvicorn app.main:app --reload --port 8001
```

- Runs on http://localhost:8001 (interactive docs at `/docs`).
- See [`backend/README.md`](backend/README.md) for the full endpoint list and
  troubleshooting (e.g. the `externally-managed-environment` pip error).

### 2. Frontend (Laravel + Sanctum) — terminal 2

```bash
cd frontend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite       # default DB is sqlite
php artisan migrate
php artisan serve --port=8000
```

- Runs on http://localhost:8000 (health at `/up`).
- Set `ANIME_SAMA_BASE_URL` (default `http://localhost:8001`) and, if the
  backend enforces one, `ANIME_SAMA_API_KEY` in `frontend/.env`. The same key
  goes in `backend/.env` as `ANIMESAMA_API_KEY` (leave both empty in local dev).

---

## API (Laravel gateway)

Base URL: `http://localhost:8000/api`

### Authentication (public)

| Method | Endpoint          | Body                                                    |
|--------|-------------------|---------------------------------------------------------|
| POST   | `/auth/register`  | `name, email, password, password_confirmation`          |
| POST   | `/auth/login`     | `email, password` → returns `{ token }`                 |

Send the returned token as `Authorization: Bearer <token>` on protected routes.

### Catalogue browsing (public, proxied to FastAPI)

| Method | Endpoint                          | Query                          |
|--------|-----------------------------------|--------------------------------|
| GET    | `/anime/search`                   | `q`                            |
| GET    | `/anime/new-episodes`             | –                              |
| GET    | `/anime/catalogue`                | `url`                          |
| GET    | `/anime/episodes`                 | `url`, optional `prefer`       |
| GET    | `/anime/site`                     | –                              |

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

# Add a favorite (authenticated)
curl -s -X POST http://localhost:8000/api/favorites \
  -H "Authorization: Bearer <TOKEN>" -H 'Content-Type: application/json' \
  -d '{"catalogue_url":"https://anime-sama.fr/catalogue/naruto/","name":"Naruto"}'
```

---

## Testing

```bash
cd frontend && php artisan test        # Laravel feature/unit tests
```

The Laravel tests mock the backend with `Http::fake`, so they do not require a
running FastAPI service or network access.

---

## Notes & legal

- The anime-sama domain changes periodically. Set `ANIMESAMA_SITE_URL` or call
  `POST /api/site/resolve` on the backend to auto-detect the current domain.
- This project reads publicly available data through the upstream
  `anime-sama_api` library and is intended for **personal/educational use**.
  Respect the terms of service of the sites involved.
