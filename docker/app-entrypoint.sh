#!/bin/sh
set -eu

app_root=/var/www/html
config_file="$app_root/lab-config.php"

if [ ! -f "$config_file" ]; then
    cp /usr/local/share/nealice/lab-config.php "$config_file"
fi

mkdir -p "$app_root/uploads/praticas"
chown -R www-data:www-data "$app_root/uploads"

exec docker-php-entrypoint "$@"
