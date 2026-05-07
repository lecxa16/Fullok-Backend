#!/bin/sh
set -e

cd /var/www/html

echo "[entrypoint] ensuring storage dirs (volume puede estar vacío)..."
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

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

# Si la tabla roles está vacía, asumimos primer arranque y corremos seeders.
# Idempotente: en el segundo deploy ya hay roles → no se re-seedea.
ROLES_COUNT=$(php -r "
\$pdo = new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
\$row = \$pdo->query('SELECT COUNT(*) AS c FROM roles')->fetch();
echo \$row['c'];
" 2>/dev/null || echo "0")

if [ "$ROLES_COUNT" = "0" ]; then
    echo "[entrypoint] primer arranque detectado (roles vacíos), corriendo seeders..."
    php artisan db:seed --force --no-interaction
else
    echo "[entrypoint] seeders ya aplicados (roles=$ROLES_COUNT), salto db:seed"
fi

echo "[entrypoint] caching config/routes/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] starting: $@"
exec "$@"
