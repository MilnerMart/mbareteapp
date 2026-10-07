# Despliegue con Docker

Levanta 3 contenedores: `db` (MariaDB 11.4), `backend` (API Laravel) y `frontend` (Blade + Vite compilado).

## Primer despliegue

```bash
cd deploy
cp .env.example .env
# completar .env: APP_KEYs, claves de DB, ADMIN_EMAIL / ADMIN_PASSWORD y urls publicas
echo "base64:$(openssl rand -base64 32)"   # una vez para BACKEND_APP_KEY y otra para FRONTEND_APP_KEY
docker compose up -d --build
```

- Frontend: `FRONTEND_PUBLIC_URL` (por defecto http://localhost:8080)
- API: `BACKEND_PUBLIC_URL` (por defecto http://localhost:8000). Tiene que ser accesible desde el navegador porque las imagenes se sirven desde el backend.

Al arrancar, el backend corre `migrate --force`. La migracion `2026_10_09_100000_seed_base_data_and_admin_user` crea:
core models, roles (admin / alumno / entrenador), permisos, el gimnasio base `leoncion-gym` y el usuario admin con `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
Es idempotente: si los datos ya existen no los vuelve a crear ni cambia la clave del admin.

## Actualizar

```bash
git pull
cd deploy && docker compose up -d --build
```

## Comandos utiles

```bash
docker compose logs -f backend
docker compose exec backend php artisan migrate:status
docker compose exec db mariadb -u"$DB_USERNAME" -p muscleApp
```

## Datos persistentes (volumenes)

- `db-data`: base de datos
- `backend-images`: imagenes subidas (`public/images`)
- `backend-storage`, `frontend-storage`: logs y sesiones

`docker compose down -v` borra todo eso.

## HTTPS

Poner un proxy inverso (Caddy, Nginx, Traefik) delante de los puertos 8080 y 8000 y usar `https://` en las urls publicas del `.env`.
