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

## Back4App Containers (u otro hosting de un contenedor por app)

Ahi no hay docker-compose ni `.env`: se crean 2 apps desde el repo de GitHub y las variables se cargan en el panel.
La base MySQL/MariaDB tiene que ser externa (Back4App Containers no la incluye).

| | Backend | Frontend |
|---|---|---|
| Root directory | `/` | `/` |
| Dockerfile path | `deploy/Dockerfile.backend` | `deploy/Dockerfile.frontend` |
| Puerto | 80 | 80 |

Variables del **backend**:

```
APP_KEY=base64:...            # echo "base64:$(openssl rand -base64 32)"
APP_URL=https://<url-del-backend>
ASSET_URL=https://<url-del-backend>
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
ADMIN_EMAIL=...
ADMIN_PASSWORD=...
# solo si la base exige SSL:
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
```

Variables del **frontend**:

```
APP_KEY=base64:...            # otra distinta a la del backend
APP_URL=https://<url-del-frontend>
BACKEND_API_URL=https://<url-del-backend>/api/v1
```

Las imagenes subidas se guardan en el disco del contenedor y se pierden en cada redeploy si el hosting no da volumen persistente.

## HTTPS

Poner un proxy inverso (Caddy, Nginx, Traefik) delante de los puertos 8080 y 8000 y usar `https://` en las urls publicas del `.env`.
