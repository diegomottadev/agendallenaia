#!/bin/sh
# Cachea configuracion, rutas y vistas al arrancar, con el .env del servidor ya
# montado. Se hace aca y no en el build: la imagen no conoce los secretos.
set -e
cd /app
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec "$@"
