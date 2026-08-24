#!/bin/sh
set -eu

cd /var/www/html

mkdir -p bootstrap/cache storage/app/private storage/framework/cache/data \
    storage/framework/sessions storage/framework/testing storage/framework/views storage/logs

chown -R www-data:www-data bootstrap/cache storage
find bootstrap/cache storage -type d -exec chmod 775 {} \;
find bootstrap/cache storage -type f -exec chmod 664 {} \;

if [ ! -f .env ]; then
    cp .env.example .env
    chown "${HOST_UID:-1000}:${HOST_GID:-1000}" .env
fi

if [ ! -f vendor/autoload.php ]; then
    chown -R www-data:www-data vendor
    su -s /bin/sh www-data -c 'composer install --no-interaction --prefer-dist'
fi

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force --no-interaction
    chown "${HOST_UID:-1000}:${HOST_GID:-1000}" .env
fi

exec docker-php-entrypoint "$@"
