# Backend (API) de Mbarete App: nginx + PHP-FPM en un solo contenedor, escucha en el puerto 8080.
FROM serversideup/php:8.4-fpm-nginx

WORKDIR /var/www/html

# Dependencias primero: si composer.lock no cambia, Docker reutiliza esta capa en el proximo build.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --optimize --no-interaction
