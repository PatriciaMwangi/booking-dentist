FROM php:8.2-apache

# 1. Install system dependencies needed for Composer (zip/unzip)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    && rm -rf /var/lib/apt/lists/*

# 2. Install MySQL extensions for PDO
RUN docker-php-ext-install pdo pdo_mysql

# 3. Install Composer globally inside the container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Copy your project files into the Apache web root
COPY . /var/www/html/

# 5. Run Composer inside the container to install Google Cloud libraries
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 6. Enable Apache mod_rewrite for router support
RUN a2enmod rewrite

EXPOSE 80