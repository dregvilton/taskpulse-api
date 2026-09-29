FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        libpq-dev \
        linux-headers \
        oniguruma-dev \
    && docker-php-ext-install -j$(nproc) mbstring pdo_pgsql pcntl sockets \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php-fpm"]
