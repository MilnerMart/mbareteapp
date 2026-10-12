#!/usr/bin/env bash
# Instala o actualiza los plugins "docker compose" y "docker buildx" (ultimas versiones).
#   bash deploy/install-compose.sh          sin sudo: solo para tu usuario (~/.docker/cli-plugins)
#   sudo bash deploy/install-compose.sh     para todo el servidor (/usr/local/lib/docker/cli-plugins)
# No toca el motor de Docker ni los contenedores que ya estan corriendo.
set -euo pipefail

if [[ $EUID -eq 0 ]]; then PLUGIN_DIR=/usr/local/lib/docker/cli-plugins; else PLUGIN_DIR="$HOME/.docker/cli-plugins"; fi
ARCH="$(uname -m)"
BUILDX_ARCH="$([[ "$ARCH" == "aarch64" ]] && echo arm64 || echo amd64)"

# la ultima version sale de la redireccion de releases/latest (sin pipes que corten a curl)
latestVersion() {
    local url
    url="$(curl -fsSLI -o /dev/null -w '%{url_effective}' "https://github.com/docker/$1/releases/latest")"
    url="${url##*/}"
    [[ "$url" == v* ]] || { echo "No se pudo obtener la version de $1" >&2; return 1; }
    echo "$url"
}

download() {
    curl -fsSL "$1" -o "$2.tmp"
    chmod +x "$2.tmp"
    mv "$2.tmp" "$2"
}

mkdir -p "$PLUGIN_DIR"
echo "==> Plugins de Docker en $PLUGIN_DIR"
download "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-${ARCH}" "$PLUGIN_DIR/docker-compose"
BUILDX_VERSION="$(latestVersion buildx)"
download "https://github.com/docker/buildx/releases/download/${BUILDX_VERSION}/buildx-${BUILDX_VERSION}.linux-${BUILDX_ARCH}" "$PLUGIN_DIR/docker-buildx"

docker compose version
docker buildx version
