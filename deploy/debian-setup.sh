#!/usr/bin/env bash
set -euo pipefail
# Quien invoca puede usar una umask restrictiva (operador.sh usa 077); todo lo creado aquí debe ser legible por Apache.
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
apt-get install -y apache2 mariadb-server libapache2-mod-php php-cli php-mysql php-mbstring rsync openssl cron curl gnupg
[ -z "$DOMAIN" ] || apt-get install -y certbot python3-certbot-apache

# La app fija PHP en America/Montevideo y compara contra NOW() de MariaDB: ambos deben coincidir.
timedatectl set-timezone America/Montevideo
systemctl restart mariadb

mkdir -p "$DEST"
# --chmod: el clon puede venir de un mktemp 0700 o de una umask restrictiva; Apache debe poder leer todos los archivos.
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

  ADMIN_EMAIL=${ADMIN_EMAIL:-admin@clinica-imagen.local}
  ADMIN_PASS=$(openssl rand -hex 8)
  # Las cuentas de personal solo las crea un administrador, por eso se siembra el primero con contraseña aleatoria mostrada una sola vez.
  ADMIN_HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$ADMIN_PASS")
  mariadb "$DB_NAME" <<SQL
INSERT INTO administradores (nombre, apellido, ci) VALUES ('Admin', 'Inicial', '11111111');
INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado, activo)
VALUES ('$ADMIN_EMAIL', '$ADMIN_HASH', 'administrador', LAST_INSERT_ID(), 1, 1);
SQL
  ADMIN_CRED="Administrador: $ADMIN_EMAIL / $ADMIN_PASS  (se muestra una sola vez, cambiala al entrar)"
  [ -z "${CRED_FILE:-}" ] || echo "$ADMIN_CRED" > "$CRED_FILE"
else
  mariadb "$DB_NAME" -e "ALTER TABLE pacientes ADD COLUMN IF NOT EXISTS email_anterior VARCHAR(254) NULL AFTER numero"
  mariadb "$DB_NAME" -e "ALTER TABLE cuentas ADD COLUMN IF NOT EXISTS email_nuevo VARCHAR(254) NULL AFTER verificado, DROP COLUMN IF EXISTS email_anterior"
  mariadb "$DB_NAME" -e "ALTER TABLE recepcionistas ADD COLUMN IF NOT EXISTS sucursal_id INT UNSIGNED NULL"
  mariadb "$DB_NAME" -e "ALTER TABLE citas MODIFY estado ENUM('pendiente','confirmada','rechazada','cancelada','realizada') NOT NULL DEFAULT 'pendiente'"
  for t in medicos profesionales administradores recepcionistas; do
    mariadb "$DB_NAME" -e "ALTER TABLE $t ADD COLUMN IF NOT EXISTS ci VARCHAR(15) NULL AFTER apellido, ADD UNIQUE KEY IF NOT EXISTS uq_${t}_ci (ci)"
  done
fi

chown -R root:root "$DEST"
# rsync -a copia el modo del directorio origen; el operador clona en un mktemp 0700 y eso dejaba a Apache sin acceso.
chmod 755 "$DEST"
chown root:www-data "$DEST/includes/config.php"
chown -R www-data:www-data "$DEST/storage"

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

install -m 755 deploy/respaldo.sh /usr/local/sbin/clinica-respaldo
install -m 644 deploy/config/cron-clinica-imagen /etc/cron.d/clinica-imagen
install -m 644 deploy/config/profile-clinica.sh /etc/profile.d/clinica-imagen.sh
# Clave de cifrado de respaldos (todo lo que se sube al remoto va cifrado con gpg); se crea una sola vez, solo root.
if [ ! -s /root/.clinica-respaldo.key ]; then
  (umask 077; openssl rand -base64 32 > /root/.clinica-respaldo.key)
  echo "IMPORTANTE: clave de cifrado de respaldos creada en /root/.clinica-respaldo.key." >&2
  echo "            Guárdela FUERA del servidor (clinica-respaldo clave); sin ella los respaldos remotos no se recuperan." >&2
fi

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
