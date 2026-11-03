#!/usr/bin/env bash
#
# Crée un projet Laravel neuf + les libs de scraping, puis applique overlay/.
# Usage : ./setup.sh [nom-du-dossier]   (defaut: fstream-app)
#
set -euo pipefail

APP_DIR="${1:-fstream-app}"
OVERLAY_DIR="$(cd "$(dirname "$0")" && pwd)/overlay"

echo "==> 1/5  Création du projet Laravel dans '$APP_DIR'"
composer create-project laravel/laravel "$APP_DIR"
cd "$APP_DIR"

echo "==> 2/5  Dépendances de scraping (DomCrawler + CssSelector)"
composer require symfony/dom-crawler symfony/css-selector

echo "==> 3/5  Application des fichiers du portail (overlay/)"
cp -r "$OVERLAY_DIR"/. .

echo "==> 4/5  Environnement (SQLite + base URL)"
touch database/database.sqlite
grep -q '^FSTREAM_BASE_URL=' .env || echo 'FSTREAM_BASE_URL=https://french-stream.one' >> .env
php artisan migrate --force || true

echo "==> 5/5  Terminé."
echo "Démarre le serveur : cd $APP_DIR && php artisan serve"
echo "Puis ouvre http://127.0.0.1:8000"
