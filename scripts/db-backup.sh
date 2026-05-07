#!/bin/bash
# scripts/db-backup.sh [tag]
# Genera /root/fullok/backups/fullok-YYYYMMDD-HHMMSS[-tag].sql.gz y rota a 14 días

set -euo pipefail

TAG="${1:-cron}"
BACKUP_DIR="/root/fullok/backups"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
FILE="$BACKUP_DIR/fullok-$TIMESTAMP-$TAG.sql.gz"

mkdir -p "$BACKUP_DIR"

if ! docker ps --format '{{.Names}}' | grep -q '^fullok-prod_db$'; then
    echo "[backup] fullok-prod_db no está corriendo, saltando"
    exit 0
fi

ENV_FILE="/root/fullok/backend/.env"
if [ ! -f "$ENV_FILE" ]; then
    echo "[backup] no se encontró $ENV_FILE"
    exit 1
fi

DB_NAME=$(grep -E '^DB_DATABASE=' "$ENV_FILE" | cut -d'=' -f2-)
DB_ROOT_PASS=$(grep -E '^DB_ROOT_PASSWORD=' "$ENV_FILE" | cut -d'=' -f2-)

echo "[backup] generando $FILE"
docker exec fullok-prod_db sh -c "exec mysqldump --single-transaction --quick --lock-tables=false -u root -p'$DB_ROOT_PASS' '$DB_NAME'" \
    | gzip -9 > "$FILE"

SIZE=$(du -h "$FILE" | cut -f1)
echo "[backup] OK ($SIZE)"

echo "[backup] rotando: borrando backups > 14 días"
find "$BACKUP_DIR" -name 'fullok-*.sql.gz' -type f -mtime +14 -print -delete
