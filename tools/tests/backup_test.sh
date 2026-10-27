#!/bin/bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
TMP_TEST=$(mktemp -d)
trap 'rm -rf -- "$TMP_TEST"' EXIT
export BACKUP_DIR="$TMP_TEST/backups" UPLOADS_DIR="$TMP_TEST/uploads" ESTADO_DIR="$TMP_TEST/estado"
export DB_NAME=naser_test DB_HOST=unused DB_ROOT_PASS=unused
mkdir -p "$BACKUP_DIR" "$UPLOADS_DIR" "$ESTADO_DIR"
source "$ROOT/docker/backup.sh"
check() { if ! eval "$1"; then echo "FAIL: $2"; exit 1; fi; echo "OK: $2"; }
date() { if [ "$1" = '+%u' ]; then echo 1; else command date "$@"; fi; }
mariadb-dump() { echo 'partial'; return 1; }
if hacer_backup >/dev/null 2>&1; then echo 'FAIL: dump fallido aceptado'; exit 1; fi
check "[ ! -f '$BACKUP_DIR/db_$(date +%F).sql.gz' ]" 'No publica una extraccion fallida'
check "grep -q '\"estado\":\"error\"' '$ESTADO_DIR/backup-intento.json'" 'Registra el error del intento'
mariadb-dump() { echo 'CREATE TABLE prueba(id INT);'; }
hacer_backup >/dev/null 2>&1
check "gzip -t '$BACKUP_DIR/db_$(date +%F).sql.gz'" 'Publica una copia comprimida valida'
check "grep -q '\"estado\":\"correcto\"' '$ESTADO_DIR/backup-exito.json'" 'Conserva la evidencia del ultimo exito'
date() { if [ "$1" = '+%u' ]; then echo 7; else command date "$@"; fi; }
tar() { return 1; }
if hacer_backup >/dev/null 2>&1; then echo 'FAIL: tar fallido aceptado'; exit 1; fi
check 'necesita_backup' 'Reintenta documentos aunque ya exista la copia de base'
unset -f tar
hacer_backup >/dev/null 2>&1
check "tar tzf '$BACKUP_DIR/uploads_$(date +%F).tar.gz' >/dev/null" 'El archivo de documentos se puede leer'
if necesita_backup; then echo 'FAIL: repite una copia completa'; exit 1; fi
echo 'OK: No repite la copia completa del dia'

# El modo de una revisión debe comunicar el fallo al supervisor.
cat > "$TMP_TEST/mariadb-dump" <<'MOCK'
#!/bin/bash
exit 1
MOCK
chmod +x "$TMP_TEST/mariadb-dump"
mkdir -p "$TMP_TEST/once" "$TMP_TEST/once-estado"
if PATH="$TMP_TEST:$PATH" BACKUP_DIR="$TMP_TEST/once" ESTADO_DIR="$TMP_TEST/once-estado" NASER_BACKUP_ONCE=1 bash "$ROOT/docker/backup.sh" >/dev/null 2>&1; then
  echo 'FAIL: modo once oculto un fallo'; exit 1
fi
echo 'OK: El modo once devuelve fallo si la copia no termina'
BACKUP_HORA=08 BACKUP_DIAS=08 source "$ROOT/docker/backup.sh"
check '[ "$HORA" = 8 ] && [ "$DIAS" = 8 ]' 'Normaliza configuraciones con cero inicial sin octales ni JSON invalido'