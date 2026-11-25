#!/bin/sh
set -eu

mkdir -p /var/www/html/storage/proyectos /var/www/html/temp_uploads
chown -R www-data:www-data /var/www/html/storage /var/www/html/temp_uploads

exec "$@"
