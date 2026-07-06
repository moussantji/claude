# FZ Movies — Laravel + FastAPI (fzmovies-api)

Petit projet de démonstration : un **frontend Laravel (PHP)** qui consomme un
**backend Python (FastAPI)** enveloppant le SDK
[`fzmovies-api`](https://github.com/Simatwa/fzmovies-api).

```
┌──────────────────┐        HTTP/JSON        ┌──────────────────────┐
│  Frontend Laravel │  ───────────────────▶  │  Microservice Python │
│  (Blade + Guzzle) │  ◀───────────────────  │  FastAPI + fzmovies   │
│  port 8080        │                         │  port 8000            │
└──────────────────┘                         └──────────┬───────────┘
                                                          │ scraping
                                                          ▼
                                                    fzmovies.net
```

Laravel et Python ne peuvent pas s'exécuter dans le même processus : le SDK est
en Python, Laravel est en PHP. Ils communiquent donc en HTTP/JSON.

## Démarrage rapide (Docker)

```bash
docker compose up --build
```

- Frontend Laravel : http://localhost:8080
- API Python (docs Swagger) : http://localhost:8000/docs

> Le build télécharge les dépendances (pip + composer create-project), une
> connexion internet est donc nécessaire lors du premier `up`.

## Structure

```
fzmovies-site/
├── docker-compose.yml
├── movie-api/                 # Backend Python / FastAPI
│   ├── app/
│   │   ├── main.py            # Endpoints REST
│   │   └── service.py         # Wrapper autour de fzmovies-api
│   ├── requirements.txt
│   └── Dockerfile
└── laravel-frontend/          # Frontend Laravel
    ├── Dockerfile             # composer create-project + overlay
    └── app-overlay/           # Nos fichiers appliqués sur un Laravel neuf
        ├── app/Http/Controllers/MovieController.php
        ├── app/Services/FzMoviesClient.php
        ├── config/movieapi.php
        ├── routes/web.php
        └── resources/views/...
```

## Endpoints de l'API Python

| Méthode | Endpoint              | Description                                  |
|---------|-----------------------|----------------------------------------------|
| GET     | `/health`             | Liveness probe                               |
| GET     | `/api/meta`           | Enumérations (searchby, catégories, genres)  |
| GET     | `/api/search`         | Recherche (`q`, `searchby`, `category`)      |
| GET     | `/api/browse`         | Listes filtrées (`filter`, `category`, ...)  |
| GET     | `/api/movie-files`    | Fichiers d'un film (`url`)                   |
| GET     | `/api/download-links` | Liens de téléchargement d'un fichier (`url`) |

## Lancer les services séparément (sans Docker)

**Backend :**
```bash
cd movie-api
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8000
```

**Frontend :**
```bash
# Créer un Laravel neuf puis copier le contenu de laravel-frontend/app-overlay/ dedans
composer create-project laravel/laravel my-front
cp -r laravel-frontend/app-overlay/. my-front/
cd my-front
echo "MOVIE_API_URL=http://localhost:8000" >> .env
php artisan serve --port=8080
```

## Avertissement

Ce projet n'est **pas affilié** à fzmovies.net. Le SDK effectue du *scraping*
d'un site tiers de films protégés par le droit d'auteur. À réserver à un usage
**personnel / éducatif** ; un usage public vous expose à des risques légaux
(copyright) et de fiabilité (le scraping casse si le site change).
