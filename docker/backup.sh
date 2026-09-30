#!/bin/bash
# ==========================================
# NASER SGI - Copias de seguridad automaticas
# - Base de datos: todos los dias a la hora BACKUP_HORA -> backups/db_AAAA-MM-DD.sql.gz
# - Documentos (uploads): los domingos                  -> backups/uploads_AAAA-MM-DD.tar.gz
# - Se borran las copias con mas de BACKUP_DIAS dias
# ==========================================
HORA=${BACKUP_HORA:-2}
DIAS=${BACKUP_DIAS:-7}
mkdir -p /backups

hacer_backup() {
  local hoy; hoy=$(date +%F)
  echo "[$(date '+%F %T')] Backup de la base ${DB_NAME}..."
  if mariadb-dump -h "$DB_HOST" -uroot -p"$DB_ROOT_PASS" --single-transaction --routines --triggers "$DB_NAME" | gzip > "/backups/db_${hoy}.sql.gz.tmp"; then
    mv "/backups/db_${hoy}.sql.gz.tmp" "/backups/db_${hoy}.sql.gz"
    echo "  OK db_${hoy}.sql.gz"
  else
    rm -f "/backups/db_${hoy}.sql.gz.tmp"
    echo "  ERROR en el backup de la base"
  fi
  if [ "$(date +%u)" = "7" ] && [ ! -f "/backups/uploads_${hoy}.tar.gz" ]; then
    tar czf "/backups/uploads_${hoy}.tar.gz" -C / uploads && echo "  OK uploads_${hoy}.tar.gz"
  fi
  find /backups -maxdepth 1 -name 'db_*.sql.gz' -mtime +"$DIAS" -delete
  find /backups -maxdepth 1 -name 'uploads_*.tar.gz' -mtime +$((DIAS * 4)) -delete
}

# Primer backup al arrancar (si todavia no hay uno de hoy)
[ -f "/backups/db_$(date +%F).sql.gz" ] || hacer_backup

while true; do
  sleep 600
  if [ "$(date +%-H)" -ge "$HORA" ] && [ ! -f "/backups/db_$(date +%F).sql.gz" ]; then
    hacer_backup
  fi
done
