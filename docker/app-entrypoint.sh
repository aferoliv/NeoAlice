#!/bin/sh
set -eu

app_root=/var/www/html
config_file="$app_root/lab-config.php"

if [ ! -f "$config_file" ]; then
    cp /usr/local/share/nealice/lab-config.php "$config_file"
fi

mkdir -p "$app_root/uploads/praticas"

# uploads/ e um volume nomeado, entao o uploads/.htaccess do repositorio nao
# aparece aqui dentro. Recria a protecao no volume. A regra que realmente vale
# na imagem e a de docker/security.conf; esta e redundancia.
if [ ! -f "$app_root/uploads/.htaccess" ]; then
    cat > "$app_root/uploads/.htaccess" <<'HTACCESS'
php_flag engine off
Options -ExecCGI -Indexes
AddType text/plain .php .phtml .php3 .php4 .php5 .php7 .phps .pl .py .cgi .sh
<FilesMatch "\.(?i:php|phtml|php[0-9]|phps|pl|py|cgi|sh)$">
    Require all denied
</FilesMatch>
HTACCESS
fi

chown -R www-data:www-data "$app_root/uploads"
chmod -R u=rwX,go=rX "$app_root/uploads"

exec docker-php-entrypoint "$@"
