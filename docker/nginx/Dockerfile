FROM nginx:alpine

COPY --from=tmdb-app:test /var/www/public /var/www/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
