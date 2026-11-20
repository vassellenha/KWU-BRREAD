#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "Notice: APP_KEY not set, generating application key..."
    php artisan key:generate --force
fi

echo "Running database migrations..."
if php artisan migrate --force; then
    echo "Running database seeders..."
    php artisan db:seed --force || echo "Database seeding completed or skipped."
else
    echo "Warning: Database migration failed. Verify your database connection parameters in .env"
fi

if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
else
    echo "Development mode: ensuring routes and views are dynamic..."
    php artisan route:clear || true
    php artisan view:clear || true
    php artisan config:clear || true
fi

# Everything above ran as root, and artisan (the seeders included) writes cache entries, compiled
# views and logs as it goes. A root-owned storage/framework/cache/data stops the www-data workers
# from writing any new cache entry, which turns every rate-limited route (the sign-in captcha, the
# sign-in form) and the /admin honeypot into a 500. Hand the writable folders back to www-data.
chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true

export PORT="${PORT:-8080}"
echo "Rendering nginx config for port ${PORT}..."
envsubst '${PORT}' < /etc/nginx/templates/app.conf.template > /etc/nginx/sites-enabled/app.conf
nginx -t

echo "Starting nginx + php-fpm on port ${PORT} (via supervisord)..."
exec supervisord -c /etc/supervisor/conf.d/app.conf
