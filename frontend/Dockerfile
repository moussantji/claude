FROM php:8.3-cli

# System deps + PHP extensions required by Laravel.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies first (better layer caching).
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --no-progress --prefer-dist || true

# Copy the application.
COPY . .

RUN composer install --no-interaction --no-progress --prefer-dist \
    && cp -n .env.example .env || true

EXPOSE 8000

# Generate key, migrate and serve. Uses sqlite by default.
CMD php artisan key:generate --force \
    && touch database/database.sqlite \
    && php artisan migrate --force \
    && php artisan serve --host=0.0.0.0 --port=8000
