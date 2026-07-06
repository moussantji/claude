# Portail visuel French Stream (Laravel)

Portail **Laravel** qui scrape `french-stream.one` (DataLife Engine) et affiche
un catalogue visuel : accueil par sections, listes Films/Séries/Genres,
recherche, **scroll infini**, page détail avec **bande-annonce YouTube** intégrée.

Contrairement à `fs-api` (Node.js, marqué *obsolète* par son auteur et écrit pour
l'ancien `french-stream.co` aujourd'hui fermé), ce portail est écrit **from scratch
en PHP** à partir de la structure HTML réelle de `french-stream.one`.

## Installation

```bash
cd french-stream-laravel
chmod +x setup.sh
./setup.sh
cd fstream-app
php artisan serve
```
→ http://127.0.0.1:8000

## Structure de l'overlay

```
overlay/
├── app/Services/FrenchStream/
│   ├── FsClient.php     # HTTP (headers navigateur, cookies, gestion Cloudflare)
│   ├── FsParser.php     # parsing DomCrawler (.short, .short-title, .film-version…)
│   └── FsScraper.php    # API haut niveau + cache : home / listing / search / detail
├── app/Http/Controllers/PortalController.php
├── config/fstream.php
├── routes/web.php       # /, /browse, /search, /film/{id}, /more
└── resources/views/...  # layout + cards/grid (scroll infini) + home/list/search/detail
```

## Ce qui fonctionne (basé sur le vrai HTML)

- **Accueil** : sections « Nouveautés Films », « Nouveautés Séries », « Box Office »…
- **Catégories** : Films, Séries, VF, genres, années (menu configurable dans `PortalController::MENU`).
- **Recherche** : `?do=search`.
- **Scroll infini** : pagination `?cstart=N` suivie automatiquement.
- **Cartes** : affiche, titre, qualité (HD/CAM…), version (VF/VOSTFR), badge épisodes.
- **Page détail** : affiche + synopsis + **bande-annonce YouTube** (l'ID est présent dans le HTML).

## ⚠️ Limites importantes (à lire)

1. **Cloudflare** : `french-stream.one` est derrière Cloudflare. Un simple `Http::get`
   côté serveur peut recevoir un **403/challenge**. Le client envoie des en-têtes de
   navigateur pour maximiser les chances, mais si Cloudflare bloque, il faudra passer
   par un résolveur type **FlareSolverr** (ou une IP autorisée). Le code renvoie un
   message clair dans ce cas.
2. **Lecteurs de streaming** : la page détail (`/index.php?newsid=…`) n'a pas pu être
   analysée depuis mon environnement (pas d'accès réseau). Le parseur récupère pour
   l'instant les `iframe` de manière générique. **Pour brancher les vrais lecteurs,
   envoie-moi le code source d'une page de film** et j'ajuste `FsParser::detail()`.
   Les **bandes-annonces YouTube**, elles, marchent déjà.
3. **Non testé en réel** : code écrit d'après le HTML fourni mais non exécuté contre
   le site vivant.

## Note légale

french-stream diffuse des contenus sous copyright sans autorisation. Ce projet est
fourni à des fins **d'apprentissage / usage personnel**. Un déploiement public
t'expose à de réels risques juridiques.
