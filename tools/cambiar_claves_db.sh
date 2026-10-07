#!/usr/bin/env bash
# ==========================================================
# Cambia las claves de la base de datos (DB_PASS y DB_ROOT_PASS),
# pone APP_ENV=production y reinicia el sistema.
#
# Uso (en el servidor, desde la carpeta del proyecto):
#     bash tools/cambiar_claves_db.sh
#
# Hace, en este orden:
#   1) backup de la base (backups/antes_cambio_claves.sql) y copia del .env
#   2) genera 2 claves nuevas al azar
#   3) las cambia DENTRO de la base
#   4) las escribe en el .env (+ APP_ENV=production)
#   5) reinicia (docker compose up -d)
#   6) prueba que la clave nueva funcione
# Al final MUESTRA las claves una sola vez: guardalas en un gestor de claves.
# ==========================================================
set -euo pipefail
export MSYS_NO_PATHCONV=1          # Git Bash en Windows: no tocar rutas tipo /algo
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "ERROR: no existe .env en $(pwd)"; exit 1; }
docker compose ps db >/dev/null 2>&1 || { echo "ERROR: Docker no responde. Abri Docker Desktop y reintenta."; exit 1; }
docker compose exec -T db true >/dev/null 2>&1 || { echo "ERROR: el contenedor 'db' no esta corriendo (docker compose up -d)."; exit 1; }

leer_env() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | tr -d '\r' || true; }
poner_env() {   # poner_env CLAVE VALOR
  if grep -qE "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else printf '\n%s=%s\n' "$1" "$2" >> .env; fi
}

DB_USER_ACTUAL="$(leer_env DB_USER)"; DB_USER_ACTUAL="${DB_USER_ACTUAL:-naser}"
FECHA="$(date +%Y%m%d_%H%M%S)"
mkdir -p backups

echo "1/6  Backup de la base y copia del .env..."
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --triggers "$MARIADB_DATABASE"' > backups/antes_cambio_claves.sql
[ -s backups/antes_cambio_claves.sql ] || { echo "ERROR: el backup quedo vacio. No se cambio nada."; exit 1; }
cp .env "backups/env_antes_cambio_claves_$FECHA.bak"
echo "     OK ($(du -h backups/antes_cambio_claves.sql | cut -f1))"

echo "2/6  Generando claves nuevas..."
NUEVA_PASS="$(openssl rand -hex 16)"
NUEVA_ROOT="$(openssl rand -hex 16)"

echo "3/6  Cambiando las claves dentro de la base..."
printf "ALTER USER IF EXISTS '%s'@'%%' IDENTIFIED BY '%s';\nALTER USER IF EXISTS 'root'@'%%' IDENTIFIED BY '%s';\nALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '%s';\nFLUSH PRIVILEGES;\n" \
  "$DB_USER_ACTUAL" "$NUEVA_PASS" "$NUEVA_ROOT" "$NUEVA_ROOT" \
  | docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD"'
echo "     OK"

echo "4/6  Actualizando el .env..."
poner_env DB_PASS "$NUEVA_PASS"
poner_env DB_ROOT_PASS "$NUEVA_ROOT"
poner_env APP_ENV production
echo "     OK"

echo "5/6  Reiniciando el sistema..."
docker compose up -d >/dev/null 2>&1
sleep 8

echo "6/6  Probando la clave nueva..."
if docker compose exec -T db mariadb -u"$DB_USER_ACTUAL" -p"$NUEVA_PASS" -e "SELECT 1" >/dev/null 2>&1; then
  echo "     OK: el usuario $DB_USER_ACTUAL entra con la clave nueva."
else
  echo "ERROR: la clave nueva no funciona. El .env anterior esta en backups/env_antes_cambio_claves_$FECHA.bak"
  exit 1
fi

cat <<FIN

==================== LISTO ====================
Guarda estas claves en un gestor de claves (no se vuelven a mostrar):

  DB_PASS      = $NUEVA_PASS
  DB_ROOT_PASS = $NUEVA_ROOT

APP_ENV quedo en production.
Pendiente: revisar APP_URL en el .env (https://sgi.gruponaser.com.ar)
y entrar al sistema para comprobar que carga.
===============================================
FIN
