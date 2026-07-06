# FZ Movies — Laravel pur (scraping PHP + API Sanctum)

Version **100 % Laravel**, **sans Docker ni Python**. Le scraping de fzmovies
(qui était fait par la lib Python `fzmovies-api`) est ici **porté en PHP**, et
une **API JSON protégée par Laravel Sanctum** est exposée en plus du site web.

> Pourquoi cette version ? Dans la version précédente (Docker), le frontend
> appelait un service Python via l'URL `http://movie-api:8000`, qui n'existe
> **que dans le réseau Docker**. Lancé hors Docker, cet appel partait vers un
> serveur injoignable → la page « tournait en boucle » jusqu'au timeout.
> Ici, tout est dans Laravel : plus de service externe, donc plus ce problème.

## Installation (script automatique)

```bash
cd fzmovies-laravel
chmod +x setup.sh
./setup.sh            # crée un projet Laravel "fzmovies-app" prêt à l'emploi
cd fzmovies-app
php artisan serve
```
Ouvre http://127.0.0.1:8000

## Installation manuelle

```bash
# 1. Projet Laravel neuf
composer create-project laravel/laravel fzmovies-app
cd fzmovies-app

# 2. Dépendances de scraping
composer require symfony/dom-crawler symfony/css-selector

# 3. API + Sanctum
php artisan install:api

# 4. Copier les fichiers du dépôt par-dessus
cp -r ../fzmovies-laravel/overlay/. .

# 5. Base de données (SQLite par défaut) + utilisateur démo
touch database/database.sqlite
echo 'FZMOVIES_BASE_URL=https://fzmovies.live' >> .env
php artisan migrate --seed

# 6. Lancer
php artisan serve
```

## Ce qui est ajouté (dossier `overlay/`)

```
app/Services/FzMovies/
├── FzMoviesClient.php     # HTTP + gestion du cookie de session (PHPSESSID)
├── Parser.php             # parsing HTML (Symfony DomCrawler) — port de handlers.py
└── FzMoviesScraper.php    # API haut niveau : search / browse / movieFiles / downloadLinks
app/Http/Controllers/
├── MovieController.php         # site web (Blade), utilise le scraper directement
└── Api/
    ├── AuthController.php      # login/logout → jetons Sanctum
    └── MovieApiController.php  # API JSON (protégée par Sanctum)
app/Models/User.php             # + trait HasApiTokens
config/fzmovies.php             # URL source, timeouts, mapping catégories
routes/web.php                  # /, /browse, /movie, /download
routes/api.php                  # /api/login + endpoints protégés
resources/views/...             # UI Blade (Tailwind)
database/seeders/DatabaseSeeder.php  # utilisateur démo
```

## Utiliser l'API (Sanctum)

**1. Obtenir un jeton :**
```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" \
  -d "email=demo@example.com&password=password"
# => { "token": "1|xxxxxxxx...", "user": {...} }
```

**2. Appeler un endpoint protégé :**
```bash
TOKEN="1|xxxxxxxx..."
curl "http://127.0.0.1:8000/api/search?q=Fast+X" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

| Méthode | Endpoint                   | Auth      | Description                         |
|---------|----------------------------|-----------|-------------------------------------|
| POST    | `/api/login`               | —         | Récupère un jeton Sanctum           |
| POST    | `/api/logout`              | Sanctum   | Révoque le jeton courant            |
| GET     | `/api/user`                | Sanctum   | Utilisateur courant                 |
| GET     | `/api/search`              | Sanctum   | `q`, `searchby`, `category`         |
| GET     | `/api/browse`              | Sanctum   | `filter`, `category`, `genre`, `year` |
| GET     | `/api/movie-files`         | Sanctum   | `url` (page du film)                |
| GET     | `/api/download-links`      | Sanctum   | `url` (fichier)                     |

## Notes importantes

- **Test en conditions réelles** : je n'ai pas pu exécuter le scraping contre le
  vrai site depuis mon environnement (pas d'accès réseau sortant). Le code est un
  portage fidèle de la lib Python, mais si un sélecteur HTML a changé côté
  fzmovies, dis-le moi avec le message d'erreur et j'ajuste `Parser.php`.
- Si la recherche renvoie une erreur réseau, essaie un miroir dans `.env` :
  `FZMOVIES_BASE_URL=https://fzmovies.host`.
- Le scraping vise un site tiers de films sous copyright : à réserver à un usage
  **personnel / éducatif**.



## Nouveautés

### Scroll infini
Les pages de recherche et « Parcourir » chargent automatiquement la page
suivante quand tu arrives en bas (via l'endpoint `GET /more?next=<url>` +
`IntersectionObserver`). Plus besoin de bouton « page suivante ».

### Lecteur vidéo intégré (comme YouTube)
Chaque fichier a un bouton **▶ Regarder** qui ouvre un lecteur HTML5.
Fonctionnement :

1. `GET /watch?url=<fichier>` résout la chaîne complète jusqu'à l'**URL directe**
   du fichier (`download1.php` → `download.php` → `dlink.php` → lien final).
2. Cette URL est stockée côté serveur derrière un **jeton de session** (aucune
   URL fournie par l'utilisateur n'est proxyfiée → pas de SSRF).
3. `GET /stream/{token}` relaie le flux avec support des **requêtes Range**
   (donc on peut avancer/reculer dans la vidéo) et le bon `Referer`.

**Limites honnêtes :**
- Ne marche bien que pour les fichiers **.mp4** (le navigateur ne lit pas
  nativement .mkv/.avi → dans ce cas, télécharge et lis avec VLC).
- Le serveur `php artisan serve` est mono-thread : le streaming occupe le
  worker. Pour un usage réel, sers l'app derrière **php-fpm + nginx**.
- Non testé contre le vrai site depuis mon environnement (pas de réseau
  sortant) : si un lien direct ne se lit pas, envoie-moi le comportement.

## À propos des films « en français »

fzmovies ne propose **pas** de catégorie française. Les catégories du site sont
`All`, `Bollywood`, `Hollywood` et `DHollywood` (Hollywood doublé — en hindi,
pas en français). Il n'est donc pas possible de filtrer « uniquement les films
français » depuis cette source. L'interface, elle, est déjà en français.
Si tu veux du contenu francophone, il faudrait brancher une **autre source** —
dis-le moi et on en discute.
