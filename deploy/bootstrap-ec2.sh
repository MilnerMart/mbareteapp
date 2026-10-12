#!/usr/bin/env bash
# Prepara una EC2 nueva (Amazon Linux 2023 o Ubuntu) para correr Mbarete App con Docker.
# Se corre una sola vez:  sudo bash deploy/bootstrap-ec2.sh
# Instala Docker + Compose + Buildx, git y el cliente de MariaDB, y agrega 2 GB de swap (la t3.micro tiene 1 GB de RAM).
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "Correlo con sudo"; exit 1; }
APP_USER="${SUDO_USER:-ec2-user}"
. /etc/os-release

echo "==> Swap de 2 GB"
if ! grep -q "^/swapfile " /proc/swaps; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Docker, git y cliente MariaDB ($ID)"
if [[ "$ID" == "ubuntu" ]]; then
    apt-get update -y
    apt-get install -y git mariadb-client ca-certificates curl
    command -v docker >/dev/null || curl -fsSL https://get.docker.com | sh
elif [[ "$ID" == "amzn" ]]; then
    dnf install -y docker git mariadb105
    ARCH="$(uname -m)"
    BUILDX_ARCH="$([[ "$ARCH" == "aarch64" ]] && echo arm64 || echo amd64)"
    PLUGIN_DIR=/usr/local/lib/docker/cli-plugins
    mkdir -p "$PLUGIN_DIR"
    curl -fsSL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-${ARCH}" -o "$PLUGIN_DIR/docker-compose"
    # la ultima version sale de la redireccion de releases/latest (sin pipes que corten a curl)
    BUILDX_VERSION="$(curl -fsSLI -o /dev/null -w '%{url_effective}' https://github.com/docker/buildx/releases/latest)"
    BUILDX_VERSION="${BUILDX_VERSION##*/}"
    [[ "$BUILDX_VERSION" == v* ]] || { echo "No se pudo obtener la version de buildx"; exit 1; }
    curl -fsSL "https://github.com/docker/buildx/releases/download/${BUILDX_VERSION}/buildx-${BUILDX_VERSION}.linux-${BUILDX_ARCH}" -o "$PLUGIN_DIR/docker-buildx"
    chmod +x "$PLUGIN_DIR/docker-compose" "$PLUGIN_DIR/docker-buildx"
else
    echo "Sistema no soportado: $ID"; exit 1
fi

systemctl enable --now docker
usermod -aG docker "$APP_USER"

echo
docker --version
docker compose version
docker buildx version
echo
echo "Listo. Cierra la sesion SSH y vuelve a entrar para usar docker sin sudo."
