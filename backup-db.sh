#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if [ -f .env ]; then set -a; . ./.env; set +a; fi
mkdir -p backups
DB_NAME="${DB_NAME:-naser_sgi_prueba}"
DB_ROOT_PASS="${DB_ROOT_PASS:-root2026}"
FILE="backups/naser_$(date +%Y%m%d_%H%M%S).sql"
docker compose exec -T db mariadb-dump -uroot -p"$DB_ROOT_PASS" --single-transaction --routines --triggers "$DB_NAME" > "$FILE"
echo "Backup creado: $FILE"
