#!/usr/bin/env bash
#
# Crée un projet Laravel neuf, installe Sanctum + les libs de scraping,
# puis applique les fichiers de ce dépôt (overlay/).
#
# Usage :  ./setup.sh [nom-du-dossier]   (defaut: fzmovies-app)
#
set -euo pipefail

APP_DIR="${1:-fzmovies-app}"
OVERLAY_DIR="$(cd "$(dirname "$0")" && pwd)/overlay"

echo "==> 1/6  Création du projet Laravel dans '$APP_DIR'"
composer create-project laravel/laravel "$APP_DIR"
cd "$APP_DIR"

echo "==> 2/6  Dépendances de scraping (DomCrawler + CssSelector)"
composer require symfony/dom-crawler symfony/css-selector

echo "==> 3/6  Installation de l'API + Laravel Sanctum"
php artisan install:api --no-interaction || true

echo "==> 4/6  Application des fichiers du projet (overlay/)"
cp -r "$OVERLAY_DIR"/. .

echo "==> 5/6  Configuration de l'environnement"
touch database/database.sqlite
grep -q '^FZMOVIES_BASE_URL=' .env || echo 'FZMOVIES_BASE_URL=https://fzmovies.live' >> .env

echo "==> 6/6  Migrations + utilisateur de démo (demo@example.com / password)"
php artisan migrate --seed --force

echo ""
echo "OK. Démarre le serveur avec :"
echo "    cd $APP_DIR && php artisan serve"
echo "Frontend : http://127.0.0.1:8000"
