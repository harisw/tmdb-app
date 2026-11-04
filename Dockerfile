# ========================
# 1. Node build stage
# ========================
FROM node:26-alpine AS frontend

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
      icu-dev \
      unzip \
      autoconf g++ make \
   && docker-php-ext-install pdo pdo_pgsql mbstring zip pcntl intl \
   && pecl install redis \
   && docker-php-ext-enable redis

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
RUN apk add --no-cache libpq libzip oniguruma icu-libs icu-data-full bash jq aws-cli postgresql17-client

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# App PHP overrides (memory_limit, upload sizes, timeouts) — FPM + CLI/Horizon
COPY docker/php/zz-app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/zz-fpm.conf /usr/local/etc/php-fpm.d/zz-fpm.conf

COPY --from=builder /var/www/vendor vendor
COPY . .
COPY --from=frontend /app/public/build public/build

RUN --mount=type=bind,from=composer:2,source=/usr/bin/composer,target=/usr/bin/composer \
    composer dump-autoload --optimize --no-dev --no-scripts --no-interaction

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
