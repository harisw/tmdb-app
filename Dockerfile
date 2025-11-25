# Stage 1: Build frontend assets with Node
FROM node:22-bookworm AS node-builder

WORKDIR /var/www

# Copy package files
COPY package*.json ./
RUN npm ci

# Copy source files needed for Vite build
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

# Build assets for production
RUN npm run build

# Verify build succeeded
RUN ls -la public/build/ && \
    test -f public/build/manifest.json || (echo "❌ Manifest not found!" && exit 1)

# Stage 2: PHP application with Nginx
FROM richarvey/nginx-php-fpm:3.1.6

# Copy application code
COPY . .

# Copy built assets from node-builder stage
COPY --from=node-builder /var/www/public/build ./public/build

# Verify assets are in place
RUN ls -la public/build/manifest.json || (echo "❌ Manifest missing in final stage!" && exit 1)

# Image configuration
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1

# Laravel configuration
ENV APP_ENV=production
ENV APP_DEBUG=true
ENV LOG_CHANNEL=stderr

# Allow composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER=1

CMD ["/start.sh"]
