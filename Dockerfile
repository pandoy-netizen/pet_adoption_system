
FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Keep only the prefork MPM
RUN a2dismod mpm_event mpm_worker || true

RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

# Show MPM configuration during build
RUN echo "=== APACHE MPM CONFIGURATION ===" \
    && grep -RniE 'LoadModule[[:space:]]+mpm_' \
       /etc/apache2/ || true

# Validate Apache configuration
RUN apache2ctl -t

# Create a diagnostic startup script
RUN printf '%s\n' \
    '#!/bin/sh' \
    'echo "=== RUNTIME MPM CONFIGURATION ==="' \
    'grep -RniE "LoadModule[[:space:]]+mpm_" /etc/apache2/ || true' \
    'echo "=== APACHE CONFIG TEST ==="' \
    'apache2ctl -t' \
    'exec apache2-foreground' \
    > /usr/local/bin/start-app \
    && chmod +x /usr/local/bin/start-app

EXPOSE 80

CMD ["start-app"]
