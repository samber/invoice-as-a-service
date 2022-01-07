FROM php:7.4-fpm

# Update
RUN apt-get update -y && \
    apt-get upgrade -y

# Install Composer
RUN apt-get update -y && \
    apt-get install -y git zip
RUN curl -sS https://getcomposer.org/installer | php -- \
--install-dir=/usr/bin --filename=composer && chmod +x /usr/bin/composer 
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install App
COPY /src /var/www/html
CMD bash -c "composer install && composer update && php artisan serve"
EXPOSE 8000
