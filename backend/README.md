# Anime-Sama Backend (FastAPI)

A thin REST layer over the [`anime-sama_api`](https://github.com/Sky-NiniKo/anime-sama_api)
Python library. It scrapes anime-sama and exposes the data as JSON so the
Laravel + Sanctum application can consume it.

## Endpoints

| Method | Path                  | Description                                        |
|--------|-----------------------|----------------------------------------------------|
| GET    | `/health`             | Health check + current site URL + version          |
| GET    | `/api/site`           | Current anime-sama base URL                         |
| POST   | `/api/site/resolve`   | Auto-detect the current anime-sama domain           |
| GET    | `/api/search?q=`      | Search catalogues                                   |
| GET    | `/api/new-episodes`   | Latest episode releases (homepage)                  |
| GET    | `/api/catalogue?url=` | Catalogue detail (synopsis, seasons, ...)           |
| GET    | `/api/episodes?url=`  | Episodes + player links for a season URL            |

Interactive docs are available at `/docs` once the server is running.

All `/api/*` routes require the `X-API-Key` header **when** `ANIMESAMA_API_KEY`
is set. Leave it empty for open local development.

## Run locally

```bash
cd backend
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env          # then edit as needed
uvicorn app.main:app --reload --port 8001
```

## Run with Docker

```bash
docker build -t anime-sama-backend .
docker run -p 8001:8001 --env-file .env anime-sama-backend
```

## Notes

- The anime-sama domain changes periodically. Set `ANIMESAMA_SITE_URL` or call
  `POST /api/site/resolve` to auto-detect it.
- This project only reads publicly available data via the upstream library and
  is intended for personal/educational use.
