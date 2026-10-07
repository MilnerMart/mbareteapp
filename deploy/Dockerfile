# Imagen multi-stage para backend (API) y frontend (Blade).
# El contexto de build es la raiz del repo (ver docker-compose.yml).

# ---------- Base PHP-FPM + nginx ----------
FROM php:8.4-fpm-alpine AS php-base

RUN apk add --no-cache nginx supervisor su-exec icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY deploy/docker/nginx.conf /etc/nginx/http.d/default.conf
COPY deploy/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]

# ---------- Backend (API) ----------
FROM php-base AS backend

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/images \
    && chown -R www-data:www-data storage bootstrap/cache public/images

ENV APP_ROLE=backend

# ---------- Assets del frontend (Vite) ----------
FROM node:22-alpine AS frontend-assets
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# ---------- Frontend (Blade) ----------
FROM php-base AS frontend

COPY frontend/composer.json frontend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY frontend/ ./
COPY --from=frontend-assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ROLE=frontend
