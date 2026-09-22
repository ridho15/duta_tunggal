#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.duta-tunggal.yml"
ENV_FILE="$ROOT_DIR/.env.docker"
BACKUP_DIR="$ROOT_DIR/storage/backups/mysql"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_FILE="$BACKUP_DIR/duta_tunggal-${STAMP}.sql.gz"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "File $ENV_FILE belum ada." >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "docker tidak ditemukan di host." >&2
    exit 1
fi

set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

mkdir -p "$BACKUP_DIR"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql \
    mysqldump -uroot --single-transaction --routines --triggers "$DB_DATABASE" \
    | gzip > "$BACKUP_FILE"

echo "Backup tersimpan di $BACKUP_FILE"