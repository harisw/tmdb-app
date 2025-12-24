# ========================
# 1. Node build stage
# ========================
FROM node:20-alpine AS node

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build


# ========================
# 2. PHP runtime stage
# ========================
FROM php:8.3-fpm-alpine

WORKDIR /var/www

RUN apk add --no-cache \
    postgresql-dev \
    bash \
    zip \
    unzip \
    git \
    oniguruma-dev \
 && docker-php-ext-install pdo pdo_pgsql mbstring

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Copy built assets
COPY --from=node /app/public/build ./public/build

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
