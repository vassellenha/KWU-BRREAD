FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        git unzip curl libsqlite3-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_sqlite zip mbstring bcmath \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build \
    && npm prune --omit=dev \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache database

EXPOSE 8080
CMD php artisan migrate --force \
    && php artisan db:seed --force \
    && php artisan config:clear \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
