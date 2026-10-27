#!/bin/bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
TMP_TEST=$(mktemp -d)
trap 'rm -rf -- "$TMP_TEST"' EXIT
export BACKUP_DIR="$TMP_TEST/backups" ESTADO_DIR="$TMP_TEST/estado" NASER_VERIFY_ONCE=1
mkdir -p "$BACKUP_DIR" "$ESTADO_DIR"
if bash "$ROOT/docker/verificar-backup.sh"; then echo 'FAIL: acepto una copia inexistente'; exit 1; fi
grep -q '"estado":"pendiente"' "$ESTADO_DIR/verificacion-intento.json"
echo 'OK: No anuncia recuperacion sin una copia'
printf 'no gzip' > "$BACKUP_DIR/db_2026-01-01.sql.gz"
if bash "$ROOT/docker/verificar-backup.sh" >/dev/null 2>&1; then echo 'FAIL: acepto un gzip corrupto'; exit 1; fi
grep -q '"estado":"error"' "$ESTADO_DIR/verificacion-intento.json"
test ! -e "$ESTADO_DIR/verificacion-exito.json"
echo 'OK: Un archivo corrupto nunca se marca recuperado'

# Se simulan exclusivamente los binarios de MariaDB: gzip/hash/snapshot y limpieza son reales.
source "$ROOT/docker/verificar-backup.sh"
mariadb-install-db() { return 0; }
mariadbd() { while true; do sleep 1; done; }
mariadb-admin() { return 0; }
mariadb() {
  case "$*" in
    *'information_schema.TABLES'*) echo 2 ;;
    *'CREATE DATABASE'*|*'SELECT COUNT(*) FROM usuarios;'*) return 0 ;;
    *) cat >/dev/null; [ "${MOCK_RESTORE_FAIL:-0}" != 1 ] ;;
  esac
}
printf 'CREATE TABLE usuarios(id INT); CREATE TABLE documentos(id INT);\n' | gzip > "$BACKUP_DIR/db_2026-01-01.sql.gz"
verificar_backup
grep -q '"estado":"correcto"' "$ESTADO_DIR/verificacion-exito.json"
test -z "$TMP_VERIFY" && test -z "$VERIFY_PID"
echo 'OK: Conserva evidencia de restauracion y limpia la instancia temporal'
huella=$(sha256sum "$BACKUP_DIR/db_2026-01-01.sql.gz" | cut -d' ' -f1)
grep -q "\"sha256\":\"$huella\"" "$ESTADO_DIR/verificacion-exito.json"
echo 'OK: La huella corresponde al contenido restaurado'
cp "$ESTADO_DIR/verificacion-exito.json" "$TMP_TEST/exito-previo.json"
MOCK_RESTORE_FAIL=1
if verificar_backup; then echo 'FAIL: acepto una restauracion incompleta'; exit 1; fi
grep -q '"estado":"error"' "$ESTADO_DIR/verificacion-intento.json"
cmp "$TMP_TEST/exito-previo.json" "$ESTADO_DIR/verificacion-exito.json"
test -z "$TMP_VERIFY" && test -z "$VERIFY_PID"
echo 'OK: Un fallo de importacion no reemplaza el ultimo exito y limpia el proceso'