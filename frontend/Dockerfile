# Frontend de Mbarete App: compila los assets con Vite y los sirve con nginx + PHP-FPM en el puerto 8080.
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Dependencias de PHP en la imagen oficial de Composer (Alpine): asi el build no lanza procesos en la imagen final,
# lo que permite construir tambien en Docker viejos (ver deploy/compat/docker-20.10.override.yml)
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM serversideup/php:8.4-fpm-nginx

WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=vendor /app ./
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
