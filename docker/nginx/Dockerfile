FROM nginx:1.25-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=ghcr.io/harisw/tmdb-app /var/www/public /var/www/public
