# =========================================================
# Stage 1: Build Frontend Assets (Vite + Tailwind CSS v4)
# =========================================================
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources resources
COPY tailwind.config.js vite.config.js ./
RUN npm run build

# =========================================================
# Stage 2: Install Composer Dependencies
# =========================================================
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# =========================================================
# Stage 3: Production PHP Runtime
# =========================================================
FROM php:8.4-cli-bookworm AS runtime

# Install PHP extensions required by Laravel, Supabase PgSQL, SQLite, PhpSpreadsheet (gd, zip)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_sqlite \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    zip \
    gd \
    bcmath \
    opcache

# Copy composer binary for autoloader generation & artisan
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application source code
COPY . .

# Copy vendor from vendor build stage
COPY --from=vendor /app/vendor /app/vendor

# Copy compiled frontend assets from frontend build stage
COPY --from=frontend /app/public/build /app/public/build

# Finish autoloader optimization and Laravel package discovery
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p database storage bootstrap/cache \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache database \
    && chmod +x docker-entrypoint.sh

ENV PORT=8080
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8080

ENTRYPOINT ["/app/docker-entrypoint.sh"]
