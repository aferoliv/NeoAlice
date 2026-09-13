FROM php:7.4-apache

RUN docker-php-ext-install pdo_mysql

COPY docker/lab-config.php /usr/local/share/nealice/lab-config.php
COPY docker/app-entrypoint.sh /usr/local/bin/app-entrypoint

# Bloqueia .env, .git/, quimica.sql e execucao de PHP em uploads/.
# Ver docker/security.conf.
COPY docker/security.conf /etc/apache2/conf-enabled/zz-nealice-security.conf

RUN sed -i 's/\r$//' /usr/local/bin/app-entrypoint /etc/apache2/conf-enabled/zz-nealice-security.conf \
    && chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
