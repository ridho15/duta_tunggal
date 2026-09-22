#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT=18083
COMPOSE_FILE="$ROOT_DIR/docker-compose.duta-tunggal.yml"
ENV_FILE="$ROOT_DIR/.env.docker"
MYSQL_PORT="${MYSQL_PORT:-13306}"

if ! command -v docker >/dev/null 2>&1; then
    echo "docker tidak ditemukan di host." >&2
    exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
    echo "File $ENV_FILE belum ada. Siapkan dulu dari .env.docker.example." >&2
    exit 1
fi

if ss -ltnH | awk '{print $4}' | sed 's/.*://' | grep -qx "$PORT"; then
    echo "Port $PORT sudah dipakai. Ubah port dulu sebelum menjalankan container ini." >&2
    exit 1
fi

if ss -ltnH | awk '{print $4}' | sed 's/.*://' | grep -qx "$MYSQL_PORT"; then
    echo "Port MySQL $MYSQL_PORT sudah dipakai. Ubah MYSQL_PORT dulu sebelum menjalankan container ini." >&2
    exit 1
fi

cd "$ROOT_DIR"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --build
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps
