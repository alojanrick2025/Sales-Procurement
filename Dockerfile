FROM php:8.2-apache
RUN a2enmod rewrite
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy everything you uploaded
COPY . /var/www/html/

# Automatically fix the folder structure if the main folder was uploaded by accident
RUN if [ -d "/var/www/html/Sales and Procurement Management System" ]; then \
        mv "/var/www/html/Sales and Procurement Management System/"* /var/www/html/ && \
        mv "/var/www/html/Sales and Procurement Management System/".* /var/www/html/ 2>/dev/null || true && \
        rm -rf "/var/www/html/Sales and Procurement Management System"; \
    fi

# Give Apache permission to use the system's .htaccess router
RUN echo "<Directory /var/www/html>\n\tAllowOverride All\n</Directory>" > /etc/apache2/conf-available/allow-override.conf \
    && a2enconf allow-override

# Set final permissions
RUN chown -R www-data:www-data /var/www/html/
EXPOSE 80
