FROM php:8.2-apache

# Enable Apache mod_rewrite for routing
RUN a2enmod rewrite

# Install and enable mysqli extension for MySQL database connections
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy the application code to Apache's document root
COPY . /var/www/html/

# Give proper permissions to Apache
RUN chown -R www-data:www-data /var/www/html/

# Render automatically exposes port 80 for Web Services when using Docker
EXPOSE 80
