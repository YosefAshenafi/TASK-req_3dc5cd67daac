FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        bash git curl icu-dev oniguruma-dev libzip-dev libpng-dev libjpeg-turbo-dev \
        freetype-dev ffmpeg mysql-client sqlite sqlite-dev autoconf g++ make \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring intl zip gd bcmath opcache pcntl \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apk del autoconf g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN addgroup -g 1000 www && adduser -D -u 1000 -G www www \
    && mkdir -p /var/spool/smartpark /run/secrets \
    && chown -R www:www /var/www/html /var/spool/smartpark \
    && chmod +x /usr/local/bin/entrypoint.sh

USER www

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
