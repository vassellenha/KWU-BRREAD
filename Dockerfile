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
# Stage 3: Production PHP Runtime (php-fpm + nginx, not `artisan serve`)
# =========================================================
FROM php:8.4-fpm-bookworm AS runtime

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

# nginx terminates HTTP and proxies PHP requests to php-fpm over FastCGI;
# supervisor keeps both processes alive as PID 1; gettext-base provides
# envsubst to template the Railway-assigned $PORT into the nginx config.
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

# Copy composer binary for autoloader generation & artisan
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application source code
COPY . .

# Copy vendor from vendor build stage
COPY --from=vendor /app/vendor /app/vendor

# Copy compiled frontend assets from frontend build stage
COPY --from=frontend /app/public/build /app/public/build

# Review photos are scaled down in the browser first, but one that could not be (an old browser) arrives as is:
# PHP's own 2 MB upload cap would refuse it before Laravel could answer, so allow up to 8 MB.
RUN printf 'upload_max_filesize=8M\npost_max_size=10M\n' > /usr/local/etc/php/conf.d/uploads.ini

RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf.template /etc/nginx/templates/app.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf

# Finish autoloader optimization and Laravel package discovery. Request
# handling runs as www-data (php-fpm's pool default, nginx's worker user) so
# an attacker who reaches the app through a request never runs as root, even
# though the master processes below still start as root to bind the port and
# supervise their workers.
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p database storage bootstrap/cache \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database \
    && chmod +x docker-entrypoint.sh

ENV PORT=8080

# Errors go to the container log, which Railway shows, instead of a file inside the container.
# A LOG_CHANNEL variable set on the host still wins over this default.
ENV LOG_CHANNEL=stderr

EXPOSE 8080

ENTRYPOINT ["/app/docker-entrypoint.sh"]
