# ========================
# 1. Node build stage
# ========================
FROM node:25-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources resources
COPY vite.config.js .

RUN npm run build


# ========================
# 2. PHP builder stage
# ========================
FROM php:8.4-fpm-alpine AS builder

WORKDIR /var/www

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
 && docker-php-ext-install pdo pdo_pgsql mbstring zip \
 && apk del postgresql-dev libzip-dev oniguruma-dev unzip \
 && apk add --no-cache libpq libzip oniguruma

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer*.json ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts


# ========================
# 3. PHP-FPM runtime stage
# ========================
FROM php:8.4-fpm-alpine AS app

WORKDIR /var/www

# Runtime libs only — no dev headers needed, extensions copied from builder
RUN apk add --no-cache libpq libzip oniguruma

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY --from=builder /var/www/vendor vendor
COPY . .
COPY --from=frontend /app/public/build public/build

RUN chown -R www-data:www-data /var/www

EXPOSE 9000

CMD ["php-fpm"]
