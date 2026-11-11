ARG APP_IMAGE
FROM ${APP_IMAGE} AS app_source

FROM nginx:1.29-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app_source /var/www/public /var/www/public
