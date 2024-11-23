FROM php:8.3-apache

# Update Operating System
RUN apt-get update

# Install developer tools and PHP extensions
RUN apt-get install -y wget vim git zip unzip zlib1g-dev libzip-dev libpng-dev
RUN docker-php-ext-install -j$(nproc) mysqli pdo_mysql gd zip gettext pcntl exif

# Apache
COPY docker-apache-conf/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite
RUN service apache2 restart

EXPOSE 80