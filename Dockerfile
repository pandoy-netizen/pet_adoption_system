
FROM php:8.2-apache

# Install PHP MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Set application directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set file permissions
RUN chown -R www-data:www-data /var/www/html

# Create startup script to fix MPM conflict
RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    'echo "Fixing Apache MPM configuration..."' \
    'rm -f /etc/apache2/mods-enabled/mpm_event.load' \
    'rm -f /etc/apache2/mods-enabled/mpm_event.conf' \
    'rm -f /etc/apache2/mods-enabled/mpm_worker.load' \
    'rm -f /etc/apache2/mods-enabled/mpm_worker.conf' \
    'ln -sf ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load' \
    'ln -sf ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf' \
    'echo "Checking Apache configuration..."' \
    'apache2ctl -t' \
    'echo "Starting Apache..."' \
    'exec apache2-foreground' \
    > /usr/local/bin/start-app \
    && chmod +x /usr/local/bin/start-app

EXPOSE 80

CMD ["/usr/local/bin/start-app"]
