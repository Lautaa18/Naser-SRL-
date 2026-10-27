#!/bin/bash
# Respaldo diario y documentos semanales. Los estados se escriben sin credenciales.
set -o pipefail
umask 022
HORA=${BACKUP_HORA:-2}
DIAS=${BACKUP_DIAS:-7}
BACKUP_DIR=${BACKUP_DIR:-/backups}
UPLOADS_DIR=${UPLOADS_DIR:-/uploads}
ESTADO_DIR=${ESTADO_DIR:-/estado}
mkdir -p "$BACKUP_DIR" "$ESTADO_DIR" || exit 1
if ! [[ "$HORA" =~ ^[0-9]{1,2}$ && "$DIAS" =~ ^[0-9]{1,9}$ ]] || ((10#$HORA > 23 || 10#$DIAS < 1)); then
  echo 'BACKUP_HORA debe ser 0-23 y BACKUP_DIAS debe ser positivo.' >&2
  exit 1
fi
HORA=$((10#$HORA)); DIAS=$((10#$DIAS))

estado_backup() {
  local estado=$1 mensaje=$2 archivo=${3:-} ahora
  ahora=$(date +%s)
  printf '{"estado":"%s","mensaje":"%s","archivo":"%s","hora":%s,"retencion_dias":%s}\n' "$estado" "$mensaje" "$archivo" "$ahora" "$DIAS" > "$ESTADO_DIR/backup-intento.json.tmp" && mv "$ESTADO_DIR/backup-intento.json.tmp" "$ESTADO_DIR/backup-intento.json"
}
latido_backup() {
  printf '{"hora":%s,"retencion_dias":%s,"hora_programada":%s}\n' "$(date +%s)" "$DIAS" "$HORA" > "$ESTADO_DIR/backup-latido.json.tmp" && mv "$ESTADO_DIR/backup-latido.json.tmp" "$ESTADO_DIR/backup-latido.json"
}
hacer_backup() {
  local hoy archivo
  hoy=$(date +%F); archivo="db_${hoy}.sql.gz"
  estado_backup en_curso 'Realizando copia de seguridad' "$archivo" || return 1
  echo "[$(date '+%F %T')] Backup de la base..."
  if MYSQL_PWD="${DB_ROOT_PASS:-}" mariadb-dump --no-defaults -h "$DB_HOST" -uroot --single-transaction --routines --triggers "$DB_NAME" | gzip > "$BACKUP_DIR/$archivo.tmp" && gzip -t "$BACKUP_DIR/$archivo.tmp" && mv "$BACKUP_DIR/$archivo.tmp" "$BACKUP_DIR/$archivo"; then
    echo "  OK $archivo"
  else
    rm -f "$BACKUP_DIR/$archivo.tmp"
    estado_backup error 'No se pudo completar la copia de la base' "$archivo"
    echo '  ERROR en el backup de la base' >&2
    return 1
  fi
  if [ "$(date +%u)" = 7 ] && [ ! -f "$BACKUP_DIR/uploads_${hoy}.tar.gz" ]; then
    if tar czf "$BACKUP_DIR/uploads_${hoy}.tar.gz.tmp" -C "$(dirname "$UPLOADS_DIR")" "$(basename "$UPLOADS_DIR")" && gzip -t "$BACKUP_DIR/uploads_${hoy}.tar.gz.tmp" && mv "$BACKUP_DIR/uploads_${hoy}.tar.gz.tmp" "$BACKUP_DIR/uploads_${hoy}.tar.gz"; then
      echo "  OK uploads_${hoy}.tar.gz"
    else
      rm -f "$BACKUP_DIR/uploads_${hoy}.tar.gz.tmp"
      estado_backup error 'La base se copio pero fallo el archivo de documentos' "$archivo"
      echo '  ERROR en el backup de documentos' >&2
      return 1
    fi
  fi
  if ! find "$BACKUP_DIR" -maxdepth 1 -name 'db_*.sql.gz' -mtime +"$DIAS" -delete || ! find "$BACKUP_DIR" -maxdepth 1 -name 'uploads_*.tar.gz' -mtime +$((DIAS * 4)) -delete; then
    estado_backup error 'La copia se completo pero no se pudieron depurar respaldos antiguos' "$archivo"
    return 1
  fi
  estado_backup correcto 'Copia de seguridad completada' "$archivo" || return 1
  cp "$ESTADO_DIR/backup-intento.json" "$ESTADO_DIR/backup-exito.json.tmp" && mv "$ESTADO_DIR/backup-exito.json.tmp" "$ESTADO_DIR/backup-exito.json"
}
necesita_backup() {
  local hoy; hoy=$(date +%F)
  [ ! -f "$BACKUP_DIR/db_${hoy}.sql.gz" ] || { [ "$(date +%u)" = 7 ] && [ ! -f "$BACKUP_DIR/uploads_${hoy}.tar.gz" ]; }
}
# Permite pruebas con funciones simuladas sin iniciar el servicio.
if [ "${BASH_SOURCE[0]}" != "$0" ]; then return 0; fi
latido_backup || exit 1
resultado=0
if necesita_backup; then hacer_backup || resultado=$?; fi
[ "${NASER_BACKUP_ONCE:-0}" = 1 ] && exit "$resultado"
while true; do
  sleep 300
  latido_backup
  if [ "$(date +%-H)" -ge "$HORA" ] && necesita_backup; then hacer_backup; fi
done
