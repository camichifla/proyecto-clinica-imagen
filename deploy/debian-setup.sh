#!/usr/bin/env bash
# deploy/debian-setup.sh — installs Clinica Imagen on a fresh Debian VM
# (Apache + mod_php + MariaDB). Run as root from the project root:
#   sudo bash deploy/debian-setup.sh [APP_URL]
# HTTPS (Let's Encrypt): DOMAIN=clinica.example.com CERTBOT_EMAIL=you@example.com sudo -E bash deploy/debian-setup.sh
# Safe to re-run: it updates the code and leaves the database, config.php and uploads alone.
# RESET_DB=1 instead drops the database and uploaded results and starts from a fresh schema + new admin.
# CRED_FILE=<path> also writes the new admin's credentials there (operador.sh prints them at the very end).
set -euo pipefail
# Callers may run with a restrictive umask (operador.sh uses 077); everything created here must be readable by Apache.
umask 022

[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo)." >&2; exit 1; }
[ -f includes/config.sample.php ] || { echo "Run from the project root." >&2; exit 1; }

DEST=/var/www/clinica-imagen
DB_NAME=clinica_imagen
DB_USER=clinica
APP_URL=${1:-http://$(hostname -I | awk '{print $1}')}
DOMAIN=${DOMAIN:-}
if [ -n "$DOMAIN" ]; then
  [ -n "${CERTBOT_EMAIL:-}" ] || { echo "DOMAIN requires CERTBOT_EMAIL (Let's Encrypt contact)." >&2; exit 1; }
  APP_URL=https://$DOMAIN
  APP_HTTPS=true
else
  APP_HTTPS=false
fi
APP_URL=${APP_URL%/}

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y apache2 mariadb-server libapache2-mod-php php-cli php-mysql php-mbstring rsync openssl cron curl
[ -z "$DOMAIN" ] || apt-get install -y certbot python3-certbot-apache

# The app pins PHP to America/Montevideo and compares against MariaDB's NOW().
timedatectl set-timezone America/Montevideo
systemctl restart mariadb

# --chmod: the clone may come from a 0700 mktemp dir or a restrictive umask; Apache must read every file.
mkdir -p "$DEST"
rsync -a --chmod=D755,F644 \
  --exclude='.git' --exclude='.claude' --exclude='.atl' \
  --exclude='includes/config.php' --exclude='storage/mail.log' \
  --include='storage/resultados/.htaccess' --exclude='storage/resultados/*' \
  ./ "$DEST/"

if [ "${RESET_DB:-0}" = 1 ]; then
  echo "RESET_DB=1: dropping database $DB_NAME and uploaded results."
  mariadb -e "DROP DATABASE IF EXISTS \`$DB_NAME\`"
  find "$DEST/storage/resultados" -mindepth 1 ! -name '.htaccess' -delete 2>/dev/null || true
fi
mariadb -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

if [ ! -f "$DEST/includes/config.php" ]; then
  DB_PASS=$(openssl rand -hex 16)
  mariadb -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost';
              ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
              GRANT SELECT, INSERT, UPDATE, DELETE ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
  sed -e "s|define('DB_USER', 'root')|define('DB_USER', '$DB_USER')|" \
      -e "s|define('DB_PASS', '')|define('DB_PASS', '$DB_PASS')|" \
      -e "s|define('APP_KEY', '')|define('APP_KEY', '$(openssl rand -base64 32)')|" \
      -e "s|define('APP_URL', '[^']*')|define('APP_URL', '$APP_URL')|" \
      -e "s|define('APP_HTTPS', [a-z]*)|define('APP_HTTPS', $APP_HTTPS)|" \
      includes/config.sample.php > "$DEST/includes/config.php"
  chown root:www-data "$DEST/includes/config.php"
  chmod 640 "$DEST/includes/config.php"
fi

TABLES=$(mariadb -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")
if [ "$TABLES" -eq 0 ]; then
  mariadb --default-character-set=utf8mb4 "$DB_NAME" < sql/schema.sql

  # Staff accounts are only created by an administrator, so seed the first one.
  ADMIN_EMAIL=${ADMIN_EMAIL:-admin@clinica-imagen.local}
  ADMIN_PASS=$(openssl rand -hex 8)
  ADMIN_HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$ADMIN_PASS")
  mariadb "$DB_NAME" <<SQL
INSERT INTO administradores (nombre, apellido, ci) VALUES ('Admin', 'Inicial', '11111111');
INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado, activo)
VALUES ('$ADMIN_EMAIL', '$ADMIN_HASH', 'administrador', LAST_INSERT_ID(), 1, 1);
SQL
  ADMIN_CRED="Administrador: $ADMIN_EMAIL / $ADMIN_PASS  (se muestra una sola vez, cambiala al entrar)"
  [ -z "${CRED_FILE:-}" ] || echo "$ADMIN_CRED" > "$CRED_FILE"
else
  # Existing databases: columns added after the first install (idempotent).
  mariadb "$DB_NAME" -e "ALTER TABLE pacientes ADD COLUMN IF NOT EXISTS email_anterior VARCHAR(254) NULL AFTER numero"
  mariadb "$DB_NAME" -e "ALTER TABLE cuentas ADD COLUMN IF NOT EXISTS email_anterior VARCHAR(254) NULL AFTER verificado"
  mariadb "$DB_NAME" -e "ALTER TABLE recepcionistas ADD COLUMN IF NOT EXISTS sucursal_id INT UNSIGNED NULL"
  mariadb "$DB_NAME" -e "ALTER TABLE citas MODIFY estado ENUM('pendiente','confirmada','rechazada','cancelada','realizada') NOT NULL DEFAULT 'pendiente'"
  for t in medicos profesionales administradores recepcionistas; do
    mariadb "$DB_NAME" -e "ALTER TABLE $t ADD COLUMN IF NOT EXISTS ci VARCHAR(15) NULL AFTER apellido, ADD UNIQUE KEY IF NOT EXISTS uq_${t}_ci (ci)"
  done
fi

chown -R root:root "$DEST"
# rsync -a copies the source root's mode; the operator clones into a 0700 mktemp dir, which locked Apache out.
chmod 755 "$DEST"
chown root:www-data "$DEST/includes/config.php"
chown -R www-data:www-data "$DEST/storage"

# result uploads are up to 10MB per file (Debian default is 2M / 8M)
for dir in /etc/php/*/apache2/conf.d; do
  printf 'upload_max_filesize = 10M\npost_max_size = 64M\n' > "$dir/99-clinica.ini"
done

cat > /etc/apache2/sites-available/clinica-imagen.conf <<EOF
<VirtualHost *:80>
    ${DOMAIN:+ServerName $DOMAIN}
    DocumentRoot $DEST

    <Directory $DEST>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    <DirectoryMatch "^$DEST/(includes|sql|vendor|deploy|docs|storage)(/|\$)">
        Require all denied
    </DirectoryMatch>

    <FilesMatch "^(\..*|composer\.(json|lock))\$">
        Require all denied
    </FilesMatch>

    # Behind Cloudflare Tunnel every request arrives from localhost: take the real client IP
    # (used by rate limiting and the event log) from CF-Connecting-IP, trusted only from localhost.
    RemoteIPHeader CF-Connecting-IP
    RemoteIPInternalProxy 127.0.0.1 ::1

    ErrorLog \${APACHE_LOG_DIR}/clinica-imagen-error.log
    CustomLog \${APACHE_LOG_DIR}/clinica-imagen-access.log combined
</VirtualHost>
EOF

# Backups: script, cron schedule (daily DB, weekly files, daily remote) and shell environment/aliases.
install -m 755 deploy/respaldo.sh /usr/local/sbin/clinica-respaldo
install -m 644 deploy/config/cron-clinica-imagen /etc/cron.d/clinica-imagen
install -m 644 deploy/config/profile-clinica.sh /etc/profile.d/clinica-imagen.sh

a2enmod -q rewrite headers remoteip
a2dissite -q 000-default || true
a2ensite -q clinica-imagen
apache2ctl configtest
systemctl restart apache2

if [ -n "$DOMAIN" ]; then
  certbot --apache -d "$DOMAIN" --redirect --non-interactive --agree-tos -m "$CERTBOT_EMAIL"
else
  echo "WARNING: running WITHOUT HTTPS. Passwords and session cookies travel in clear text." >&2
  echo "         Re-run with DOMAIN=<your.domain> CERTBOT_EMAIL=<you@example.com> to enable it." >&2
fi

# Self-check: Apache must be able to read the app and serve both a page and the API.
fallo=0
runuser -u www-data -- test -r "$DEST/.htaccess" || { echo "ERROR: www-data cannot read $DEST/.htaccess:" >&2; namei -l "$DEST/.htaccess" >&2; fallo=1; }
for ruta in / /api/sesion.php; do
  codigo=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost$ruta" || true)
  case $codigo in
    200|301|302) ;;
    *) echo "ERROR: http://localhost$ruta answered $codigo (see /var/log/apache2/clinica-imagen-error.log)" >&2; fallo=1 ;;
  esac
done
[ "$fallo" -eq 0 ] || exit 1

echo "Done: $APP_URL"
[ -z "${ADMIN_CRED:-}" ] || echo "$ADMIN_CRED"
