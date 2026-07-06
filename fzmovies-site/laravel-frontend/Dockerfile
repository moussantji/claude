FROM php:8.3-cli

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# System deps + PHP extensions required by Laravel
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install zip mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Create a fresh Laravel application (requires network at build time)
RUN composer create-project laravel/laravel . --prefer-dist \
    && composer clear-cache

# Overlay our custom controllers, service, routes, config and views
COPY app-overlay/ ./

# Ensure an environment file and application key exist
RUN cp -n .env.example .env || true \
    && php artisan key:generate --force \
    && php artisan config:clear

EXPOSE 8000

# artisan serve is fine for a demo; use php-fpm + nginx for production
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
