#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
# Lee solo las claves que necesita del .env, SIN ejecutarlo (el .env puede tener valores con espacios, como MAIL_FROM_NAME=NASER SGI)
leer_env() { [ -f .env ] && grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" || true; }
DB_NAME="$(leer_env DB_NAME)"; DB_NAME="${DB_NAME:-naser_sgi_prueba}"
DB_ROOT_PASS="$(leer_env DB_ROOT_PASS)"; DB_ROOT_PASS="${DB_ROOT_PASS:-root2026}"
mkdir -p backups
FILE="backups/naser_$(date +%Y%m%d_%H%M%S).sql"
docker compose exec -T db mariadb-dump -uroot -p"$DB_ROOT_PASS" --single-transaction --routines --triggers "$DB_NAME" > "$FILE"
if [ ! -s "$FILE" ]; then echo "ERROR: el backup quedó vacío ($FILE). Revisá la clave de la base en el .env." >&2; rm -f "$FILE"; exit 1; fi
echo "Backup creado: $FILE"
