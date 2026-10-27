#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache \
         storage/logs \
         bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache || chown -R sail:sail storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

db_host="${DB_HOST:-pgsql}"
db_port="${DB_PORT:-5432}"
db_user="${DB_USERNAME:-sail}"
db_name="${DB_DATABASE:-laravel}"

until pg_isready -h "$db_host" -p "$db_port" -U "$db_user" -d "$db_name" -q; do
    echo "Waiting for PostgreSQL at ${db_host}:${db_port}…"
    sleep 2
done

if [ "${CONTAINER_SETUP:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force

    if [ "${DEMO_DATA:-false}" = "true" ]; then
        php artisan demo:reset
    fi
else
    until php artisan migrate:status --no-ansi 2>/dev/null | grep -q 'Ran'; do
        echo "Waiting for database migrations…"
        sleep 2
    done
fi

if [ "$1" = "php-fpm" ]; then
    nginx
fi

exec "$@"
