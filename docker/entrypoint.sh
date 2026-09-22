#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p \
    storage/framework/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

touch storage/logs/laravel.log
chown www-data:www-data storage/logs/laravel.log

exec "$@"
