# Backend (API) de Mbarete App: nginx + PHP-FPM en un solo contenedor, escucha en el puerto 8080.

# Dependencias de PHP en la imagen oficial de Composer (Alpine): asi el build no lanza procesos en la imagen final,
# lo que permite construir tambien en Docker viejos (ver deploy/compat/docker-20.10.override.yml)
FROM composer:2 AS vendor

WORKDIR /app
# si composer.lock no cambia, Docker reutiliza esta capa en el proximo build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM serversideup/php:8.4-fpm-nginx

WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=vendor /app ./
