# StreamWave — Laravel IPTV Platform

A full-featured IPTV subscription platform built with **Laravel 13**, **Blade**, **Tailwind CSS** and **SQLite**. It includes a marketing site, user accounts, a simulated subscription/checkout flow, per-user M3U playlist generation, and a complete admin panel for managing the catalogue.

> **Note:** This is a demonstration project. The checkout is simulated (no real payments are processed) and the channel stream URLs are placeholders. Integrate a real payment gateway (Stripe/PayPal) and your own stream sources for production use.

## Features

### Public site
- Landing page with hero, live stats, feature highlights, pricing preview and channel showcase
- Pricing page with plan comparison and FAQ
- Channel lineup browsable by category

### Accounts & subscriptions
- Register / login / logout
- Each user automatically receives unique IPTV credentials (username + password)
- Simulated checkout (card / PayPal / crypto options) that activates a subscription
- User dashboard showing active plan, days remaining, billing history and copy-paste streaming credentials
- **Personalised M3U playlist** endpoint that any IPTV player can consume:
  `/playlist.m3u?username=USER&password=PASS` (only served while the subscription is active)

### Admin panel (`/admin`)
- Dashboard with totals (users, channels, packages, active subs, revenue) and recent activity
- CRUD for **Packages**, **Channels** and **Categories**
- Subscription management (change status, delete)
- User management (search, grant/revoke admin)

## Tech stack
- Laravel 13 (PHP 8.2+)
- Blade templating + Tailwind CSS (via CDN — no build step required)
- SQLite (zero-config; swap `DB_CONNECTION` for MySQL/Postgres if preferred)

## Getting started

```bash
# Install dependencies
composer install

# Set up environment (already includes a generated APP_KEY if you copied .env)
cp .env.example .env
php artisan key:generate

# Create the SQLite database file
touch database/database.sqlite

# Run migrations and seed demo data
php artisan migrate:fresh --seed

# Start the dev server
php artisan serve
```

Then visit http://127.0.0.1:8000.

### Demo accounts (created by the seeder)

| Role   | Email                     | Password   |
|--------|---------------------------|------------|
| Admin  | `admin@streamwave.test`   | `password` |
| Member | `member@streamwave.test`  | `password` |

The demo member already has an active **Premium** subscription so you can immediately see the dashboard and test the playlist endpoint.

## Project structure

```
app/
  Http/Controllers/        # Public + auth controllers
  Http/Controllers/Admin/  # Admin panel controllers
  Http/Middleware/         # EnsureUserIsAdmin
  Models/                  # User, Category, Channel, Package, Subscription
database/
  migrations/              # Schema (users admin fields, categories, channels, packages, subscriptions)
  seeders/                 # DatabaseSeeder with demo catalogue + accounts
resources/views/
  layouts/                 # app.blade.php (public) + admin.blade.php
  partials/                # navbar, package-card, channel-card
  auth/, packages/, channels/, dashboard/, subscribe/, admin/
routes/web.php             # All routes
```

## Data model

- **User** `hasMany` Subscription — has `is_admin`, `iptv_username`, `iptv_password`
- **Category** `hasMany` Channel
- **Channel** `belongsTo` Category — name, logo, stream URL, quality, active flag
- **Package** `hasMany` Subscription — price, duration, channel count, max connections, features (JSON)
- **Subscription** `belongsTo` User & Package — status, amount paid, start/end dates
