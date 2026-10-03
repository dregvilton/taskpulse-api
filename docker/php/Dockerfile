FROM php:8.3-fpm-alpine AS php-base

RUN apk add --no-cache \
        libpq-dev \
        linux-headers \
        oniguruma-dev \
    && docker-php-ext-install -j$(nproc) mbstring pdo_pgsql pcntl sockets \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php-fpm"]

FROM php-base AS production

COPY . /app
COPY docker/php/production.conf /usr/local/etc/php-fpm.d/zz-taskpulse-production.conf
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader \
    && mkdir -p /app/protected/runtime/logs \
    && chown -R www-data:www-data /app/protected/runtime

FROM php-base AS development
