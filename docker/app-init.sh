#!/usr/bin/env bash
set -e

cd /var/www/html

# Install composer dependencies if vendor is missing (e.g. fresh clone).
if [ ! -f vendor/autoload.php ]; then
    echo "[init] Installing composer dependencies..."
    composer install --no-interaction --prefer-dist --no-progress
fi

# Wait for MySQL to become available.
echo "[init] Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306} ..."
until php -r "
    try {
        new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        exit(0);
    } catch (Exception \$e) {
        exit(1);
    }
" >/dev/null 2>&1; do
    echo "[init] MySQL not ready yet, retrying in 3s..."
    sleep 3
done
echo "[init] MySQL is ready."

# Make storage / bootstrap writable (important for Windows bind mounts).
if [ "$(id -u)" = "0" ]; then
    chmod -R 777 storage bootstrap/cache 2>/dev/null || true
fi

# Application bootstrap tasks.
php artisan key:generate --force --no-interaction || true
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan storage:link --no-interaction || true

echo "[init] Starting php-fpm..."
exec php-fpm