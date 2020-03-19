FROM php:7.1.33-stretch
RUN pecl install redis \
    && docker-php-ext-enable redis
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY . /usr/local/app/
WORKDIR /usr/local/app/
# CMD ["php","main.php"]
