FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache bash git curl su-exec ffmpeg mysql-client sqlite \
    && install-php-extensions pdo_mysql mbstring intl zip gd bcmath opcache pcntl redis pcov \
    && php -r "if (!extension_loaded('pcov')) { fwrite(STDERR, 'pcov extension failed to load\n'); exit(1); }"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php/php-fpm.d/zz-smartpark.conf /usr/local/etc/php-fpm.d/zz-smartpark.conf

RUN addgroup -g 1000 www && adduser -D -u 1000 -G www www \
    && mkdir -p /var/spool/smartpark /run/secrets \
    && touch /var/log/php-fpm.log /var/log/php-fpm-access.log \
    && chown www:www /var/log/php-fpm.log /var/log/php-fpm-access.log \
    && chown -R www:www /var/www/html /var/spool/smartpark \
    && chmod +x /usr/local/bin/entrypoint.sh

# Entrypoint starts as root so it can chown bind-mounted ./backend (often root-owned in CI),
# then drops to www via su-exec. Do not USER www here.

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
