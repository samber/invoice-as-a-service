FROM php:7.4.1-fpm
RUN apt-get update && apt-get install -y libmcrypt-dev \
    mysql-client libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
&& docker-php-ext-install mcrypt pdo_mysql

# Install Composer
RUN apt-get update && \
    apt-get install -y --no-install-recommends git zip
RUN curl --silent --show-error https://getcomposer.org/installer | php
COPY --from=composer /usr/bin/composer /usr/bin/composer

CMD bash -c "composer install && php artisan serve"
EXPOSE 8000
