
FROM php:8.2-apache

RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite \
    && docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
