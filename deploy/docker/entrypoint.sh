#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY no esta definido para $APP_ROLE. Revisa deploy/.env" >&2
    exit 1
fi

# Los volumenes pueden montarse con dueño root: se devuelven a www-data.
chown -R www-data:www-data storage bootstrap/cache
[ -d public/images ] && chown -R www-data:www-data public/images

artisan() {
    su-exec www-data php artisan "$@"
}

artisan config:cache
artisan route:cache
artisan view:cache

if [ "$APP_ROLE" = "backend" ]; then
    # La base tiene healthcheck, pero se reintenta por si tarda en aceptar conexiones.
    tries=0
    until artisan migrate --force; do
        tries=$((tries + 1))
        if [ "$tries" -ge 10 ]; then
            echo "ERROR: no se pudieron correr las migraciones" >&2
            exit 1
        fi
        echo "Esperando base de datos... ($tries)"
        sleep 3
    done
fi

exec supervisord -c /etc/supervisord.conf
