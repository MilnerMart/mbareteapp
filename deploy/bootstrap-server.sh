#!/usr/bin/env bash
# Prepara un servidor nuevo (EC2 con Amazon Linux 2023, o Ubuntu: EC2, droplet de DigitalOcean...) para correr Mbarete App.
# Se corre una sola vez:  sudo bash deploy/bootstrap-server.sh   (como root no hace falta sudo)
# Instala Docker + Compose + Buildx, git y el cliente de MariaDB, y agrega 2 GB de swap (para servidores de 1 GB de RAM).
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "Correlo con sudo"; exit 1; }
APP_USER="${SUDO_USER:-}"
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
else
    echo "Sistema no soportado: $ID"; exit 1
fi

systemctl enable --now docker
bash "$(dirname "$0")/install-compose.sh"
if [[ -n "$APP_USER" && "$APP_USER" != "root" ]]; then
    usermod -aG docker "$APP_USER"
fi

echo
docker --version
echo
echo "Listo. Si no entraste como root, cierra la sesion SSH y vuelve a entrar para usar docker sin sudo."
