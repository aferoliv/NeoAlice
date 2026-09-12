FROM php:7.4-apache

RUN docker-php-ext-install pdo_mysql

COPY docker/lab-config.php /usr/local/share/nealice/lab-config.php
COPY docker/app-entrypoint.sh /usr/local/bin/app-entrypoint

RUN chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
