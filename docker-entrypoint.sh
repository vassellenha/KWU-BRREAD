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

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Starting server on port ${PORT:-8080}..."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload

