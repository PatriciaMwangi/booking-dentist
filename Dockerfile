FROM php:8.2-apache
# Install MySQL extensions for PDO
RUN docker-php-ext-install pdo pdo_mysql
# Copy your project files into the web root
COPY . /var/www/html/
# Enable Apache mod_rewrite for .htaccess support
RUN a2enmod rewrite
EXPOSE 80