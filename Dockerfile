# ========================
# 1. Node build stage
# ========================
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources resources
#COPY public ./public
COPY vite.config.js .

RUN npm run build


# ========================
# 2. PHP-FPM stage
# ========================
FROM php:8.3-fpm-alpine AS app

WORKDIR /var/www

# System deps
RUN apk add --no-cache \
    postgresql-dev \
    bash \
    zip \
    libzip-dev \
    oniguruma-dev \
    unzip \
 && docker-php-ext-install pdo pdo_pgsql mbstring zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer*.json ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

COPY . .

# Copy built assets
COPY --from=frontend /app/public/build public/build

RUN chown -R www-data:www-data /var/www

EXPOSE 9000

CMD ["php-fpm"]
