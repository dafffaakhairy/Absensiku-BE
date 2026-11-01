
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    unzip \
    libpq-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-install -j"$(nproc)" \
    pdo_pgsql \
    intl \
    mbstring \
    bcmath \
    opcache \
    pcntl \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
