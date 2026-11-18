
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod 755 /var/www/html/uploads

RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    'echo "Configuring Apache..."' \
    'rm -f /etc/apache2/mods-enabled/mpm_event.load' \
    'rm -f /etc/apache2/mods-enabled/mpm_event.conf' \
    'rm -f /etc/apache2/mods-enabled/mpm_worker.load' \
    'rm -f /etc/apache2/mods-enabled/mpm_worker.conf' \
    'ln -sf ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load' \
    'ln -sf ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf' \
    'echo "Preparing persistent upload storage..."' \
    'mkdir -p /var/www/html/uploads' \
    'chown -R www-data:www-data /var/www/html/uploads' \
    'chmod 755 /var/www/html/uploads' \
    'echo "Checking Apache configuration..."' \
    'apache2ctl -t' \
    'echo "Starting Apache..."' \
    'exec apache2-foreground' \
    > /usr/local/bin/start-app \
    && chmod +x /usr/local/bin/start-app

EXPOSE 80

CMD ["/usr/local/bin/start-app"]
