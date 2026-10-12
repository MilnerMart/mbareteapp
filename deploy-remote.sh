#!/usr/bin/env bash
# Opera el servidor por SSH desde tu Mac, parado en la carpeta muscleApp:
#   ./deploy-remote.sh setup                 primera vez: clona el repo e instala Docker en el servidor
#   ./deploy-remote.sh push-env              sube deploy/env/{backend,frontend}.env y deploy/env/server.env (como .env)
#   ./deploy-remote.sh deploy [rama]         git pull + build + migraciones + reinicio (corre deploy.sh alla)
#   ./deploy-remote.sh db-export [archivo]   descarga un dump de la base que usa el servidor
#   ./deploy-remote.sh db-import archivo     reemplaza la base del servidor con un dump
#   ./deploy-remote.sh logs [servicio]       ultimas lineas de log (frontend, backend, caddy)
#   ./deploy-remote.sh ssh                   abre una terminal en la carpeta del proyecto
# Configuracion: deploy/env/remote.env. Para otro servidor: REMOTE_ENV=deploy/env/remote.aws.env ./deploy-remote.sh ...
set -euo pipefail

cd "$(dirname "$0")"

step() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m! %s\033[0m\n' "$1" >&2; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

REMOTE_ENV="${REMOTE_ENV:-deploy/env/remote.env}"
[[ -f "$REMOTE_ENV" ]] || fail "Falta $REMOTE_ENV (copia deploy/env/remote.env.example y completalo)"
# shellcheck disable=SC1090
source "$REMOTE_ENV"
[[ -n "${SERVER:-}" ]] || fail "Falta SERVER en $REMOTE_ENV (ej. root@164.90.1.2)"
APP_DIR="${APP_DIR:-mbareteapp}"
BRANCH="${BRANCH:-main}"
REPO="${REPO:-https://github.com/MilnerMart/mbareteapp.git}"
DB_CONTAINER="${DB_CONTAINER:-}"
DB_NAME="${DB_NAME:-}"

SSH_OPTS=(-o ServerAliveInterval=30)
if [[ -n "${SSH_KEY:-}" ]]; then
    [[ -f "$SSH_KEY" ]] || fail "No existe la clave SSH $SSH_KEY"
    SSH_OPTS+=(-i "$SSH_KEY")
fi
remote() { ssh "${SSH_OPTS[@]}" "$SERVER" "$@"; }
# ssh une los argumentos con espacios: se escapan para que uno vacio (ej. sin DB_CONTAINER) no desaparezca
remoteScript() { ssh "${SSH_OPTS[@]}" "$SERVER" "bash -s -- $(printf '%q ' "$@")"; }
upload() { scp -q "${SSH_OPTS[@]}" "$1" "$SERVER:$2"; }

# Se antepone a los scripts de base de datos que corren en el servidor. Argumentos: carpeta, contenedor, base.
# Usa las credenciales DB_* de deploy/env/backend.env; con contenedor corre el cliente adentro de el (docker exec),
# sin contenedor usa el cliente instalado en el servidor (base externa, ej. RDS).
REMOTE_DB_LIB=$(cat <<'LIB'
set -euo pipefail
cd "$HOME/$1"
CONTAINER="$2"
ENV=deploy/env/backend.env
[[ -f "$ENV" ]] || { echo "Falta $ENV en el servidor (./deploy-remote.sh push-env)" >&2; exit 1; }
envval() { grep -m1 "^$1=" "$ENV" | cut -d= -f2- | sed 's/^"//;s/"$//'; }
DB_USER="$(envval DB_USERNAME)"
DB_PASS="$(envval DB_PASSWORD)"
DB_NAME="${3:-$(envval DB_DATABASE)}"
dbtool() {
    local tool="$1" bin; shift
    if [[ -n "$CONTAINER" ]]; then
        bin="$(docker exec "$CONTAINER" sh -c "command -v $tool || command -v mariadb${tool#mysql}")" \
            || { echo "No encuentro $tool en el contenedor $CONTAINER" >&2; return 1; }
        docker exec -i -e MYSQL_PWD="$DB_PASS" "$CONTAINER" "$bin" -h 127.0.0.1 -u "$DB_USER" "$@"
    else
        bin="$(command -v "$tool" || command -v "mariadb${tool#mysql}")" \
            || { echo "Falta el cliente de MySQL/MariaDB en el servidor" >&2; return 1; }
        MYSQL_PWD="$DB_PASS" "$bin" -h "$(envval DB_HOST)" -P "$(envval DB_PORT || echo 3306)" -u "$DB_USER" "$@"
    fi
}
LIB
)

REMOTE_EXPORT=$(cat <<'SCRIPT'
DUMP_OPTS=(--single-transaction --no-tablespaces --skip-add-locks --default-character-set=utf8mb4)
# mysqldump de MySQL 8 agrega lineas de GTID que fallan al importar en otra base
if [[ "$(dbtool mysqldump --help </dev/null 2>/dev/null | grep -c set-gtid-purged || true)" -gt 0 ]]; then
    DUMP_OPTS+=(--set-gtid-purged=OFF)
fi
dbtool mysqldump "${DUMP_OPTS[@]}" "$DB_NAME" </dev/null
SCRIPT
)

REMOTE_IMPORT=$(cat <<'SCRIPT'
trap 'rm -f deploy/dumps/import.sql' EXIT
dbtool mysql "$DB_NAME" < deploy/dumps/import.sql
echo "Tablas en $DB_NAME: $(dbtool mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME'" </dev/null)"
SCRIPT
)

REMOTE_SETUP=$(cat <<'SCRIPT'
set -euo pipefail
SUDO=""; [[ "$(id -u)" -eq 0 ]] || SUDO=sudo
if ! command -v git >/dev/null; then
    if command -v apt-get >/dev/null; then $SUDO apt-get update -y && $SUDO apt-get install -y git; else $SUDO dnf install -y git; fi
fi
[[ -d "$HOME/$1/.git" ]] || git clone "$2" "$HOME/$1"
cd "$HOME/$1"
if command -v docker >/dev/null && docker info >/dev/null 2>&1; then
    # servidor que ya usa Docker (ej. con otros contenedores): no se toca el motor, solo compose y buildx para este usuario
    echo "Docker ya esta instalado: se instalan compose y buildx para $(whoami)"
    bash deploy/install-compose.sh
elif [[ -z "$SUDO" ]] || sudo -n true 2>/dev/null; then
    $SUDO bash deploy/bootstrap-server.sh
else
    echo "Falta Docker y sudo pide contraseña. Entra con ./deploy-remote.sh ssh y corre: sudo bash deploy/bootstrap-server.sh" >&2
    exit 1
fi
SCRIPT
)

case "${1:-}" in
    setup)
        step "Preparando $SERVER: repositorio en ~/$APP_DIR y Docker Compose"
        remoteScript "$APP_DIR" "$REPO" <<<"$REMOTE_SETUP"
        echo "Siguiente paso: ./deploy-remote.sh push-env"
        ;;

    push-env)
        step "Subiendo variables a $SERVER:~/$APP_DIR"
        remote "mkdir -p ~/$APP_DIR/deploy/env"
        for envFile in backend frontend; do
            [[ -f "deploy/env/$envFile.env" ]] || fail "Falta deploy/env/$envFile.env"
            upload "deploy/env/$envFile.env" "$APP_DIR/deploy/env/$envFile.env" && echo "deploy/env/$envFile.env"
        done
        if [[ -f deploy/env/server.env ]]; then
            upload deploy/env/server.env "$APP_DIR/.env" && echo "deploy/env/server.env -> .env"
        fi
        remote "chmod 600 ~/$APP_DIR/deploy/env/*.env; [ -f ~/$APP_DIR/.env ] && chmod 600 ~/$APP_DIR/.env || true"
        ;;

    deploy)
        DEPLOY_BRANCH="${2:-$BRANCH}"
        git fetch -q origin "$DEPLOY_BRANCH" 2>/dev/null || true
        if git rev-parse -q --verify "$DEPLOY_BRANCH" >/dev/null && git rev-parse -q --verify "origin/$DEPLOY_BRANCH" >/dev/null \
            && [[ "$(git rev-parse "$DEPLOY_BRANCH")" != "$(git rev-parse "origin/$DEPLOY_BRANCH")" ]]; then
            warn "Tu rama local $DEPLOY_BRANCH no coincide con origin/$DEPLOY_BRANCH: el servidor despliega lo que esta en GitHub (haz git push)."
        fi
        step "Desplegando $DEPLOY_BRANCH en $SERVER"
        remote "cd ~/$APP_DIR && ./deploy.sh $(printf '%q' "$DEPLOY_BRANCH")"
        ;;

    db-export)
        OUT="${2:-deploy/dumps/muscleApp-$(date +%Y%m%d-%H%M%S).sql}"
        mkdir -p "$(dirname "$OUT")"
        step "Exportando la base de $SERVER${DB_CONTAINER:+ (contenedor $DB_CONTAINER)} a $OUT"
        printf '%s\n%s\n' "$REMOTE_DB_LIB" "$REMOTE_EXPORT" | remoteScript "$APP_DIR" "$DB_CONTAINER" "$DB_NAME" > "$OUT" \
            || { rm -f "$OUT"; fail "No se pudo exportar la base"; }
        # los clientes MariaDB 11+ agregan una primera linea que MySQL y clientes viejos no entienden
        sed -i.bak '1{/enable the sandbox mode/d;}' "$OUT" && rm -f "$OUT.bak"
        TABLES="$(grep -c '^CREATE TABLE' "$OUT" || true)"
        [[ "$TABLES" -gt 0 ]] || { rm -f "$OUT"; fail "El dump salio vacio"; }
        chmod 600 "$OUT"
        echo "$OUT: $TABLES tablas, $(du -h "$OUT" | cut -f1)"
        ;;

    db-import)
        IN="${2:-}"
        [[ -f "$IN" ]] || fail "Uso: ./deploy-remote.sh db-import archivo.sql"
        grep -q '^CREATE TABLE' "$IN" || fail "$IN no parece un dump de MySQL/MariaDB"
        warn "Esto REEMPLAZA las tablas de la base${DB_NAME:+ $DB_NAME} en $SERVER${DB_CONTAINER:+ (contenedor $DB_CONTAINER)} con $IN ($(grep -c '^CREATE TABLE' "$IN") tablas)."
        read -r -p "Escribe 'importar' para continuar: " CONFIRM
        [[ "$CONFIRM" == "importar" ]] || fail "Cancelado"
        remote "mkdir -p ~/$APP_DIR/deploy/dumps"
        step "Subiendo $IN"
        upload "$IN" "$APP_DIR/deploy/dumps/import.sql"
        step "Importando"
        printf '%s\n%s\n' "$REMOTE_DB_LIB" "$REMOTE_IMPORT" | remoteScript "$APP_DIR" "$DB_CONTAINER" "$DB_NAME"
        ;;

    logs)
        remote "cd ~/$APP_DIR && docker compose logs --tail=100 ${2:-}"
        ;;

    ssh)
        ssh -t "${SSH_OPTS[@]}" "$SERVER" "cd ~/$APP_DIR && exec \$SHELL -l"
        ;;

    *)
        sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'
        exit 1
        ;;
esac
