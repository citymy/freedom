FROM php:8.2-fpm-alpine as fpm

RUN apk add --no-cache \
    postgresql-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Allow www-data (uid 1000) to own the workdir and run composer
RUN apk add --no-cache shadow \
    && usermod -u 1000 www-data \
    && groupmod -g 1000 www-data

WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

FROM fpm as cli
