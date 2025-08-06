# Use an official PHP 8.2 image with Composer
FROM php:8.2-apache

# Install required PHP extensions (for Telegram API/webhook/file I/O)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache .htaccess
RUN a2enmod rewrite

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Copy files
COPY . /var/www/html/

# Set permissions: allow web server and PHP to write files
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Install Composer and Telegram API library
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev

# Expose port 80
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
