#!/bin/bash
# Setup script for Torrent Editor
# Run: chmod +x setup.sh && ./setup.sh

set -e

echo "=== Torrent Editor Setup ==="
echo ""

# 1. PHP dependencies
echo "[1/5] Installing PHP dependencies..."
composer install --no-interaction --optimize-autoloader
echo ""

# 2. Node dependencies + build
echo "[2/5] Building frontend assets..."
npm install
npm run build
echo ""

# 3. Laravel setup
echo "[3/5] Setting up Laravel..."
cp -n .env.example .env 2>/dev/null || true
php artisan key:generate --no-interaction 2>/dev/null || true
php artisan migrate --force
php artisan db:seed --force
echo ""

# 4. Storage
echo "[4/5] Setting up storage..."
mkdir -p storage/app/torrents
php artisan storage:link 2>/dev/null || true
echo ""

# 5. Python libtorrent (for magnet links only)
echo "[5/5] Installing Python libtorrent (for magnet link feature)..."
if python3 -c "import libtorrent" 2>/dev/null; then
    echo "  libtorrent already installed."
elif command -v apt &>/dev/null; then
    echo "  Installing via apt..."
    sudo apt install -y python3-libtorrent 2>/dev/null || {
        echo "  apt install failed. Trying pip with venv..."
        python3 -m venv venv
        venv/bin/pip install libtorrent
        echo "  Installed in venv. Set TORRENT_PYTHON=venv/bin/python3 in .env"
        echo "TORRENT_PYTHON=$(pwd)/venv/bin/python3" >> .env
    }
elif command -v pip3 &>/dev/null; then
    echo "  Installing via pip..."
    pip3 install libtorrent 2>/dev/null || {
        echo "  pip failed. Creating venv..."
        python3 -m venv venv
        venv/bin/pip install libtorrent
        echo "TORRENT_PYTHON=$(pwd)/venv/bin/python3" >> .env
    }
else
    echo "  WARNING: Could not install libtorrent. Magnet links won't work."
    echo "  Install manually: sudo apt install python3-libtorrent"
    echo "  Or: python3 -m venv venv && venv/bin/pip install libtorrent"
fi
echo ""

echo "=== Setup Complete ==="
echo ""
echo "Admin credentials:"
echo "  Email:    moussantjidiallo@gmail.com"
echo "  Password: admin1234"
echo ""
echo "Start the server:"
echo "  php artisan serve"
echo ""
