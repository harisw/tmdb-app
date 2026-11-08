FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev libzip-dev oniguruma-dev icu-dev icu-data-full unzip git \
   && apk add --no-cache --virtual .build-deps autoconf g++ make linux-headers \
   && docker-php-ext-install pdo pdo_pgsql mbstring zip pcntl intl \
   && pecl install redis \
   && docker-php-ext-enable redis \
   && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
