# Anime-Sama Backend (FastAPI)

A thin REST layer over the [`anime-sama_api`](https://github.com/Sky-NiniKo/anime-sama_api)
Python library. It calls the library directly (no re-implementation) and
exposes the data as JSON so the Laravel + Sanctum application can consume it.

## Endpoints

| Method | Path                  | Description                                        |
|--------|-----------------------|----------------------------------------------------|
| GET    | `/health`             | Health check + current site URL + version          |
| GET    | `/api/site`           | Current anime-sama base URL                         |
| POST   | `/api/site/resolve`   | Auto-detect the current anime-sama domain           |
| GET    | `/api/search?q=`      | Search catalogues                                   |
| GET    | `/api/new-episodes`   | Latest episode releases (homepage)                  |
| GET    | `/api/planning`       | Release planning, grouped into scheduled slots      |
| GET    | `/api/catalogue?url=` | Catalogue detail (synopsis, seasons, ...)           |
| GET    | `/api/episodes?url=`  | Episodes + player links for a season URL            |
| GET    | `/api/trailer?title=` | Best YouTube trailer key (TMDB, optional)           |

Interactive docs are available at `/docs` once the server is running.

All `/api/*` routes require the `X-API-Key` header **when** `ANIMESAMA_API_KEY`
is set. Leave it empty for open local development.

## Run locally

Requires Python 3.10+ (the library uses modern typing). Using [`uv`](https://docs.astral.sh/uv/):

```bash
cd backend
uv venv --python 3.12 .venv
source .venv/bin/activate
uv pip install -r requirements.txt
cp .env.example .env          # then edit as needed
uvicorn app.main:app --reload --port 8001
```

Or with plain `venv` + `pip`:

```bash
cd backend
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
uvicorn app.main:app --reload --port 8001
```

## Trailers

anime-sama exposes **no** trailer data. The `/api/trailer` endpoint looks the
title up on TMDB and returns the official YouTube trailer key. Set
`ANIMESAMA_TMDB_API_KEY` to enable it; without a key it returns
`{"youtube_key": null}` and the frontend keeps the animated poster background.

## Notes

- The anime-sama domain changes periodically. Set `ANIMESAMA_SITE_URL` or call
  `POST /api/site/resolve` to auto-detect it.
- This project only reads publicly available data via the upstream library and
  is intended for personal/educational use.
