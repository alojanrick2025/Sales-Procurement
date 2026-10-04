FROM php:8.2-apache
# headers: browser caching rules in .htaccess; deflate: gzip-compressed responses
RUN a2enmod rewrite headers deflate
RUN docker-php-ext-install mysqli opcache && docker-php-ext-enable mysqli
# APCu: shared-memory cache for the system settings (see getSystemInfo() in config.php)
RUN pecl install apcu-5.1.28 && docker-php-ext-enable apcu

# OPcache keeps compiled PHP in memory. The code never changes inside a running
# container, so files are not re-checked on every request.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=64'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'apc.shm_size=16M'; \
    } > /usr/local/etc/php/conf.d/zz-performance.ini

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
