#!/usr/bin/env bash
# deploy/respaldo.sh — standalone backup script, installed as /usr/local/sbin/clinica-respaldo.
# Usage: clinica-respaldo db|archivos|remoto|subir RUTA CARPETA|clave|descifrar ARCHIVO.gpg [SALIDA]
# (called by cron and by the operator menu)
#
# Nothing leaves the server unencrypted: remoto/subir encrypt with symmetric gpg (AES256) using $KEY_FILE and only
# the .gpg copies (kept in $ENC_DIR) are uploaded. Local backups in $BACKUP_DIR stay plaintext (root-only 0700 dir).
# The key MUST be copied off the server (`clinica-respaldo clave`); without it the remote backups cannot be restored.
# Manual restore on any machine that has the key:
#   gpg --batch --pinentry-mode loopback --passphrase-file clave.key --decrypt archivos_X.tar.gz.gpg > archivos_X.tar.gz
#   (or: clinica-respaldo descifrar archivos_X.tar.gz.gpg)   DB: gunzip -c clinica_imagen_X.sql.gz | mariadb clinica_imagen

BACKUP_DIR=/var/backups/operador
LOG_FILE=/var/log/operador.log
APP_DIR=/var/www/clinica-imagen
DB_NAME=clinica_imagen
RCLONE_REMOTE=${RCLONE_REMOTE:-gdrive:}
RETENTION_DAYS=14
KEY_FILE=${KEY_FILE:-/root/.clinica-respaldo.key}
ENC_DIR=/var/backups/operador-cifrado

umask 077
[ "$(id -u)" -eq 0 ] || { echo "Este script debe ejecutarse como root." >&2; exit 1; }
mkdir -p "$BACKUP_DIR" "$ENC_DIR"
chmod 700 "$BACKUP_DIR" "$ENC_DIR"
touch "$LOG_FILE"

log() {  # same format as operador.sh
  printf '%s [%s] [%s@%s] %s\n' "$(date '+%F %T')" "$1" "${SUDO_USER:-root}" "$(tty 2>/dev/null || echo notty)" "$2" >> "$LOG_FILE"
}
fail() { echo "✘ $1" >&2; log WARN "$1"; exit 1; }
done_ok() { echo "✔ $1"; log OK "$1"; }

# Creates the encryption key once and tells the operator to keep a copy off the server.
asegurar_clave() {
  command -v gpg >/dev/null || fail "gpg no está instalado (apt install gnupg)"
  [ -s "$KEY_FILE" ] && return 0
  openssl rand -base64 32 > "$KEY_FILE" || fail "No se pudo generar la clave de cifrado"
  chmod 600 "$KEY_FILE"
  log INFO "Clave de cifrado de respaldos creada en $KEY_FILE"
  {
    echo "⚠ Se creó la clave de cifrado de respaldos: $KEY_FILE"
    echo "  GUÁRDELA FUERA DEL SERVIDOR (gestor de contraseñas). Sin ella los respaldos remotos NO se pueden recuperar."
    echo "  Para verla: clinica-respaldo clave"
  } >&2
}

gpg_cifrar() {  # gpg_cifrar OUT  (plaintext on stdin)
  gpg --batch --yes --pinentry-mode loopback --passphrase-file "$KEY_FILE" --symmetric --cipher-algo AES256 -o "$1"
}

# Uploads $ENC_DIR to the remote; callers fill it with .gpg files only.
subir_cifrados() {
  command -v rclone >/dev/null || { log INFO "rclone no instalado; se omite el respaldo remoto"; echo "rclone no instalado: omitido"; exit 0; }
  if ! rclone listremotes | grep -qx "$RCLONE_REMOTE"; then
    log INFO "Remote $RCLONE_REMOTE no configurado (rclone config); se omite el respaldo remoto"
    echo "Remote $RCLONE_REMOTE no configurado: omitido"; exit 0
  fi
  rclone copy "$ENC_DIR" "${RCLONE_REMOTE}clinica-imagen${1:+/$1}" --include '*.gpg'
}

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
    # shadow/gshadow (and their - backups) are excluded: password hashes have no place in a backup.
    tar -czf "$dest" --exclude=/etc/shadow --exclude=/etc/shadow- --exclude=/etc/gshadow --exclude=/etc/gshadow- /etc "$APP_DIR" 2>/dev/null
    if [ $? -le 1 ]; then done_ok "Respaldo de /etc y $APP_DIR creado en $dest"; else rm -f "$dest"; fail "Falló el respaldo de archivos"; fi ;;
  remoto)
    # Encrypt only what is not encrypted yet, then upload the .gpg copies.
    asegurar_clave
    for f in "$BACKUP_DIR"/*.sql.gz "$BACKUP_DIR"/*.tar.gz; do
      [ -f "$f" ] && [ ! -e "$ENC_DIR/$(basename "$f").gpg" ] || continue
      gpg_cifrar "$ENC_DIR/$(basename "$f").gpg" < "$f" || { rm -f "$ENC_DIR/$(basename "$f").gpg"; fail "Falló el cifrado de $f"; }
    done
    if subir_cifrados; then done_ok "Respaldos cifrados subidos a ${RCLONE_REMOTE}clinica-imagen"; else fail "Falló la subida de respaldos a $RCLONE_REMOTE"; fi ;;
  subir)  # subir RUTA CARPETA: archive + encrypt an arbitrary path, upload under CARPETA
    [ -e "${2:-}" ] && [ -n "${3:-}" ] || { echo "Uso: $0 subir RUTA CARPETA" >&2; exit 2; }
    asegurar_clave
    out="$ENC_DIR/$(basename "$2")_$stamp.tar.gz.gpg"
    tar -czf - "$2" 2>/dev/null | gpg_cifrar "$out" || { rm -f "$out"; fail "Falló el cifrado de $2"; }
    if subir_cifrados "$3"; then done_ok "$2 subido cifrado a ${RCLONE_REMOTE}clinica-imagen/$3"; else fail "Falló la subida de $2"; fi ;;
  clave)
    [ -s "$KEY_FILE" ] || fail "Todavía no existe la clave (se crea con el primer respaldo remoto)"
    echo "Clave de cifrado de respaldos ($KEY_FILE). Guárdela FUERA del servidor:"; cat "$KEY_FILE" ;;
  descifrar)  # descifrar ARCHIVO.gpg [SALIDA]
    [ -f "${2:-}" ] || { echo "Uso: $0 descifrar ARCHIVO.gpg [SALIDA]" >&2; exit 2; }
    out=${3:-${2%.gpg}}
    [ -s "$KEY_FILE" ] || fail "No existe $KEY_FILE (copie aquí la clave guardada)"
    gpg --batch --yes --pinentry-mode loopback --passphrase-file "$KEY_FILE" --decrypt -o "$out" "$2" 2>/dev/null \
      && done_ok "Descifrado en $out" || { rm -f "$out"; fail "Falló el descifrado de $2 (¿clave incorrecta?)"; } ;;
  *) echo "Uso: $0 db|archivos|remoto" >&2; exit 2 ;;
esac

# Retention: drop local backups older than $RETENTION_DAYS days
find "$BACKUP_DIR" "$ENC_DIR" -type f \( -name '*.sql.gz' -o -name '*.tar.gz' -o -name '*.gpg' \) -mtime +"$RETENTION_DAYS" -delete
