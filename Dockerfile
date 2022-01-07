FROM php:7.1.3-fpm
RUN apt-get update && apt-get install -y libmcrypt-dev \
    mysql-client libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
&& docker-php-ext-install mcrypt pdo_mysql

# Install Composer
RUN apt-get update && \
    apt-get install -y --no-install-recommends git zip
RUN curl -sS https://getcomposer.org/installer | php -- \
--install-dir=/usr/bin --filename=composer && chmod +x /usr/bin/composer 
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY /src /var/www/html
CMD bash -c "composer install && composer update && php artisan serve"
EXPOSE 8000
