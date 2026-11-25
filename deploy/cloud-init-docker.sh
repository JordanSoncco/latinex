#!/bin/bash
set -euxo pipefail

dnf update -y
dnf install -y docker git tar gzip openssl

systemctl enable --now docker

if ! docker compose version >/dev/null 2>&1; then
  ARCH=$(uname -m)
  mkdir -p /usr/local/lib/docker/cli-plugins
  curl -fsSL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-${ARCH}" \
    -o /usr/local/lib/docker/cli-plugins/docker-compose
  chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
fi

mkdir -p /opt/latinex/storage/proyectos /opt/latinex/temp_uploads
touch /opt/latinex/.cloud-init-ready
