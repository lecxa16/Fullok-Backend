#!/bin/sh
set -e

cd /var/www/html

echo "[entrypoint] waiting for database..."
ATTEMPTS=0
until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -gt 30 ]; then
        echo "[entrypoint] database not reachable after 30 attempts. aborting."
        exit 1
    fi
    sleep 2
done
echo "[entrypoint] database is up."

echo "[entrypoint] linking storage..."
php artisan storage:link --force || true

echo "[entrypoint] running migrations..."
php artisan migrate --force --no-interaction

echo "[entrypoint] caching config/routes/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "[entrypoint] starting: $@"
exec "$@"
