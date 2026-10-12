#!/usr/bin/env bash
# Despliega la rama indicada (o la actual) en la EC2:
#   ./deploy.sh            -> actualiza la rama en la que esta el servidor
#   ./deploy.sh develop    -> cambia a develop y la despliega
# Trae el codigo, construye las imagenes, corre migraciones y reemplaza los contenedores.
set -euo pipefail

cd "$(dirname "$0")"
BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"
COMPOSE=(docker compose)

step() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

for envFile in deploy/env/backend.env deploy/env/frontend.env; do
    [[ -f "$envFile" ]] || fail "Falta $envFile (copia ${envFile}.example y completalo)"
done

step "Actualizando codigo de la rama $BRANCH"
git fetch --prune origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"
echo "Commit: $(git log -1 --format='%h %s')"

step "Construyendo imagenes"
"${COMPOSE[@]}" build --pull

step "Corriendo migraciones (con la imagen nueva, antes de cambiar los contenedores)"
"${COMPOSE[@]}" run --rm --no-deps -e AUTORUN_ENABLED=false backend php artisan migrate --force
"${COMPOSE[@]}" run --rm --no-deps -e AUTORUN_ENABLED=false frontend php artisan migrate --force

step "Levantando contenedores"
"${COMPOSE[@]}" up -d --remove-orphans

step "Esperando que las apps respondan"
for service in backend frontend; do
    for attempt in $(seq 1 30); do
        status="$(docker inspect --format '{{.State.Health.Status}}' "$("${COMPOSE[@]}" ps -q "$service")" 2>/dev/null || echo starting)"
        [[ "$status" == "healthy" ]] && { echo "$service: ok"; break; }
        [[ "$attempt" == 30 ]] && { "${COMPOSE[@]}" logs --tail=50 "$service"; fail "$service no quedo saludable"; }
        sleep 3
    done
done

step "Limpiando imagenes viejas"
docker image prune -f >/dev/null

step "Despliegue terminado"
"${COMPOSE[@]}" ps
