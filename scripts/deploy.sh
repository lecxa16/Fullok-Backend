#!/bin/bash
# scripts/deploy.sh — corre desde /root/fullok/backend en el Droplet
# Pasos: backend pull → admin pull+build → docker compose up → migraciones (vía entrypoint)

set -euo pipefail

ROOT="/root/fullok"
BACKEND_DIR="$ROOT/backend"
ADMIN_DIR="$ROOT/admin"
ADMIN_DIST="$ROOT/data/admin-dist"

cd "$BACKEND_DIR"

echo "==> [1/5] Backup de DB previa al deploy"
"$BACKEND_DIR/scripts/db-backup.sh" "pre-deploy" || echo "    (sin backup, posiblemente primer deploy)"

echo "==> [2/5] Pull backend"
git fetch --prune
git reset --hard origin/main

echo "==> [3/5] Pull admin"
if [ -d "$ADMIN_DIR/.git" ]; then
    git -C "$ADMIN_DIR" fetch --prune
    git -C "$ADMIN_DIR" reset --hard origin/main
else
    echo "    !! $ADMIN_DIR no es un repo git. Cloná el admin primero."
    exit 1
fi

echo "==> [4/5] Build admin (Vite → static dist)"
mkdir -p "$ADMIN_DIST"
docker run --rm \
    -v "$ADMIN_DIR":/app \
    -v "$ADMIN_DIST":/dist \
    -w /app \
    node:20-alpine sh -c "
        if [ ! -f .env.production ]; then
            cp .env.production.example .env.production
        fi
        npm ci --no-audit --no-fund
        npm run build
        rm -rf /dist/* && cp -r dist/. /dist/
    "

echo "==> [5/5] docker compose up --build (entrypoint corre migraciones)"
docker compose -f docker-compose.prod.yml up -d --build

echo "==> Limpiando imágenes viejas"
docker image prune -f

echo ""
echo "✅ Deploy completado"
docker compose -f docker-compose.prod.yml ps
