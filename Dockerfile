FROM php:8.2-apache

# Install MySQLi extension for PHP
RUN docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli

# Enable Apache rewrite and headers modules for routing/security headers
RUN a2enmod rewrite headers

# Set the document root for Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Copy application source code into Apache document root
COPY . /var/www/html/

# Expose logs folder and set correct ownership/permissions
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/logs

# Expose HTTP port 80
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
