#!/usr/bin/env bash
# deploy/respaldo.sh — standalone backup script, installed as /usr/local/sbin/clinica-respaldo.
# Usage: clinica-respaldo db|archivos|remoto   (called by cron and by the operator menu)

BACKUP_DIR=/var/backups/operador
LOG_FILE=/var/log/operador.log
APP_DIR=/var/www/clinica-imagen
DB_NAME=clinica_imagen
RCLONE_REMOTE=${RCLONE_REMOTE:-gdrive:}
RETENTION_DAYS=14

umask 077
[ "$(id -u)" -eq 0 ] || { echo "Este script debe ejecutarse como root." >&2; exit 1; }
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
touch "$LOG_FILE"

log() {  # same format as operador.sh
  printf '%s [%s] [%s@%s] %s\n' "$(date '+%F %T')" "$1" "${SUDO_USER:-root}" "$(tty 2>/dev/null || echo notty)" "$2" >> "$LOG_FILE"
}
fail() { echo "✘ $1" >&2; log WARN "$1"; exit 1; }
done_ok() { echo "✔ $1"; log OK "$1"; }

stamp=$(date +%Y%m%d_%H%M%S)

case ${1:-} in
  db)
    dest="$BACKUP_DIR/clinica_imagen_$stamp.sql.gz"
    if mariadb-dump --single-transaction "$DB_NAME" | gzip > "$dest" && [ "${PIPESTATUS[0]}" -eq 0 ]; then
      done_ok "Respaldo de base de datos creado en $dest"
    else
      rm -f "$dest"; fail "Falló el respaldo de la base de datos"
    fi ;;
  archivos)
    dest="$BACKUP_DIR/archivos_$stamp.tar.gz"
    # includes /var/www/clinica-imagen/storage/resultados (uploaded patient results)
    # GNU tar exits 1 on "file changed" warnings (archive still valid), 2 on fatal errors
    tar -czf "$dest" /etc "$APP_DIR" 2>/dev/null
    if [ $? -le 1 ]; then done_ok "Respaldo de /etc y $APP_DIR creado en $dest"; else rm -f "$dest"; fail "Falló el respaldo de archivos"; fi ;;
  remoto)
    if ! command -v rclone >/dev/null; then
      log INFO "rclone no instalado; se omite el respaldo remoto"; echo "rclone no instalado: omitido"; exit 0
    fi
    if ! rclone listremotes | grep -qx "$RCLONE_REMOTE"; then
      log INFO "Remote $RCLONE_REMOTE no configurado (rclone config); se omite el respaldo remoto"
      echo "Remote $RCLONE_REMOTE no configurado: omitido"; exit 0
    fi
    if rclone copy "$BACKUP_DIR" "${RCLONE_REMOTE}clinica-imagen"; then
      done_ok "Respaldos subidos a ${RCLONE_REMOTE}clinica-imagen"
    else
      fail "Falló la subida de respaldos a $RCLONE_REMOTE"
    fi ;;
  *) echo "Uso: $0 db|archivos|remoto" >&2; exit 2 ;;
esac

# Retention: drop local backups older than $RETENTION_DAYS days
find "$BACKUP_DIR" -type f \( -name '*.sql.gz' -o -name '*.tar.gz' \) -mtime +"$RETENTION_DAYS" -delete
