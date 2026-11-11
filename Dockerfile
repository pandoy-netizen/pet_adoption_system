
FROM php:8.2-apache

# Install PHP MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Disable conflicting Apache MPM modules
RUN a2dismod mpm_event mpm_worker || true

# Remove any enabled MPM module links
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    /etc/apache2/mods-enabled/mpm_*.conf

# Enable only prefork
RUN a2enmod mpm_prefork rewrite

# Set application directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Check Apache configuration during build
RUN apache2ctl -t

EXPOSE 80

CMD ["apache2-foreground"]
