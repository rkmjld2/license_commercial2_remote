FROM php:8.2-apache

# Install MySQLi extension required for TiDB Cloud
RUN docker-php-ext-install mysqli

# Copy PHP project files into Apache web root
COPY . /var/www/html/

# Set ownership
RUN chown -R www-data:www-data /var/www/html

# Render exposes the web service on port 80
EXPOSE 80
