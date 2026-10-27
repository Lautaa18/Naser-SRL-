#!/bin/bash
# Restaura copias en una instancia temporal SIN red. Nunca recibe credenciales reales.
set -o pipefail
umask 022
BACKUP_DIR=${BACKUP_DIR:-/backups}
ESTADO_DIR=${ESTADO_DIR:-/estado}
INTERVALO=${VERIFICAR_CADA_SEGUNDOS:-86400}
mkdir -p "$ESTADO_DIR" || exit 1
[[ "$INTERVALO" =~ ^[0-9]{1,9}$ ]] || exit 1
INTERVALO=$((10#$INTERVALO))
[ "$INTERVALO" -ge 60 ] || exit 1
TMP_VERIFY=''; VERIFY_PID=''
limpiar_verificador() {
  if [ -n "$VERIFY_PID" ] && kill -0 "$VERIFY_PID" 2>/dev/null; then
    kill "$VERIFY_PID" 2>/dev/null || true; wait "$VERIFY_PID" 2>/dev/null || true
  fi
  VERIFY_PID=''
  case "$TMP_VERIFY" in /tmp/naser-verify.*) rm -rf -- "$TMP_VERIFY" ;; esac
  TMP_VERIFY=''
}
latido_verificador() {
  printf '{"hora":%s}\n' "$(date +%s)" > "$ESTADO_DIR/verificador-latido.json.tmp" && mv "$ESTADO_DIR/verificador-latido.json.tmp" "$ESTADO_DIR/verificador-latido.json"
}
resultado_verificador() {
  local estado=$1 mensaje=$2 archivo=${3:-} hash=${4:-} tablas=${5:-0}
  printf '{"estado":"%s","mensaje":"%s","archivo":"%s","sha256":"%s","tablas":%s,"hora":%s}\n' "$estado" "$mensaje" "$archivo" "$hash" "$tablas" "$(date +%s)" > "$ESTADO_DIR/verificacion-intento.json.tmp" && mv "$ESTADO_DIR/verificacion-intento.json.tmp" "$ESTADO_DIR/verificacion-intento.json" || return 1
  if [ "$estado" = correcto ]; then
    cp "$ESTADO_DIR/verificacion-intento.json" "$ESTADO_DIR/verificacion-exito.json.tmp" && mv "$ESTADO_DIR/verificacion-exito.json.tmp" "$ESTADO_DIR/verificacion-exito.json"
  fi
}
verificar_backup() {
  local archivo hash socket tablas ready i
  archivo=$(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'db_*.sql.gz' -printf '%f\n' | sort | tail -1)
  if [ -z "$archivo" ]; then resultado_verificador pendiente 'Todavia no hay una copia automatica para restaurar'; return 1; fi
  # Solo se admiten nombres generados por backup.sh.
  [[ "$archivo" =~ ^db_[0-9]{4}-[0-9]{2}-[0-9]{2}\.sql\.gz$ ]] || { resultado_verificador error 'Nombre de copia no reconocido'; return 1; }
  resultado_verificador en_curso 'Restaurando en una base temporal aislada' "$archivo" || return 1
  TMP_VERIFY=$(mktemp -d /tmp/naser-verify.XXXXXX) || { resultado_verificador error 'No se pudo crear el espacio temporal' "$archivo"; return 1; }
  # El respaldo puede renovarse por otro proceso: hash y restauración usan la misma instantánea.
  if ! cp -- "$BACKUP_DIR/$archivo" "$TMP_VERIFY/copia.sql.gz"; then
    resultado_verificador error 'No se pudo leer la copia para verificarla' "$archivo"; limpiar_verificador; return 1
  fi
  if ! hash=$(sha256sum "$TMP_VERIFY/copia.sql.gz" | cut -d' ' -f1) || ! [[ "$hash" =~ ^[0-9a-f]{64}$ ]]; then
    resultado_verificador error 'No se pudo calcular la huella de la copia' "$archivo"; limpiar_verificador; return 1
  fi
  gzip -t "$TMP_VERIFY/copia.sql.gz" || { resultado_verificador error 'El archivo comprimido no es valido' "$archivo" "$hash"; limpiar_verificador; return 1; }
  socket="$TMP_VERIFY/mysql.sock"
  if ! mariadb-install-db --no-defaults --datadir="$TMP_VERIFY/data" --auth-root-authentication-method=normal --skip-test-db > "$TMP_VERIFY/instalar.log" 2>&1; then
    resultado_verificador error 'No se pudo preparar la base temporal' "$archivo" "$hash"; limpiar_verificador; return 1
  fi
  mariadbd --no-defaults --user="$(id -un)" --datadir="$TMP_VERIFY/data" --skip-networking --skip-log-bin --socket="$socket" --pid-file="$TMP_VERIFY/mysql.pid" --innodb-buffer-pool-size=64M --max-allowed-packet=256M > "$TMP_VERIFY/servidor.log" 2>&1 &
  VERIFY_PID=$!
  ready=0
  for i in {1..60}; do
    if mariadb-admin --no-defaults --socket="$socket" -uroot ping --silent >/dev/null 2>&1; then ready=1; break; fi
    kill -0 "$VERIFY_PID" 2>/dev/null || break
    sleep 1
  done
  if [ "$ready" != 1 ]; then resultado_verificador error 'La base temporal no inicio' "$archivo" "$hash"; limpiar_verificador; return 1; fi
  if ! mariadb --no-defaults --socket="$socket" -uroot -e 'CREATE DATABASE verificacion CHARACTER SET utf8mb4'; then
    resultado_verificador error 'No se pudo crear el destino temporal' "$archivo" "$hash"; limpiar_verificador; return 1
  fi
  if ! gzip -dc "$TMP_VERIFY/copia.sql.gz" | mariadb --no-defaults --socket="$socket" -uroot --max-allowed-packet=256M verificacion > "$TMP_VERIFY/restaurar.log" 2>&1; then
    resultado_verificador error 'La copia no se pudo restaurar completamente' "$archivo" "$hash"; limpiar_verificador; return 1
  fi
  tablas=$(mariadb --no-defaults --socket="$socket" -uroot -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='verificacion'")
  if ! [[ "$tablas" =~ ^[0-9]+$ ]] || [ "$tablas" -lt 1 ] || ! mariadb --no-defaults --socket="$socket" -uroot verificacion -e 'SELECT COUNT(*) FROM usuarios; SELECT COUNT(*) FROM documentos;' >/dev/null 2>&1; then
    resultado_verificador error 'La copia restaurada no contiene las tablas esperadas' "$archivo" "$hash"; limpiar_verificador; return 1
  fi
  if ! resultado_verificador correcto 'Copia restaurada y tablas principales consultadas' "$archivo" "$hash" "$tablas"; then limpiar_verificador; return 1; fi
  limpiar_verificador
}
# Permite probar funciones aisladas con comandos simulados.
if [ "${BASH_SOURCE[0]}" != "$0" ]; then return 0; fi
trap limpiar_verificador EXIT
trap 'limpiar_verificador; exit 0' INT TERM
while true; do
  latido_verificador || exit 1
  if verificar_backup; then resultado=0; else resultado=1; fi
  [ "${NASER_VERIFY_ONCE:-0}" = 1 ] && exit "$resultado"
  restante=$INTERVALO
  [ "$resultado" != 0 ] && restante=300
  while [ "$restante" -gt 0 ]; do
    espera=300; [ "$restante" -lt "$espera" ] && espera=$restante
    sleep "$espera"; restante=$((restante-espera)); latido_verificador
  done
done
