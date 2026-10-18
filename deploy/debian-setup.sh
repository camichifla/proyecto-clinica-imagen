#!/usr/bin/env bash
# deploy/debian-setup.sh — installs Clinica Imagen on a fresh Debian VM
# (Apache + mod_php + MariaDB). Run as root from the project root:
#   sudo bash deploy/debian-setup.sh [APP_URL]
# Safe to re-run: it updates the code and leaves the database, config.php and uploads alone.
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo)." >&2; exit 1; }
[ -f includes/config.sample.php ] || { echo "Run from the project root." >&2; exit 1; }

DEST=/var/www/clinica-imagen
DB_NAME=clinica_imagen
DB_USER=clinica
APP_URL=${1:-http://$(hostname -I | awk '{print $1}')}
APP_URL=${APP_URL%/}

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y apache2 mariadb-server libapache2-mod-php php-cli php-mysql php-mbstring rsync openssl

# The app pins PHP to America/Montevideo and compares against MariaDB's NOW().
timedatectl set-timezone America/Montevideo
systemctl restart mariadb

mkdir -p "$DEST"
rsync -a \
  --exclude='.git' --exclude='.claude' --exclude='.atl' \
  --exclude='includes/config.php' --exclude='storage/mail.log' \
  --include='storage/resultados/.htaccess' --exclude='storage/resultados/*' \
  ./ "$DEST/"

mariadb -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

if [ ! -f "$DEST/includes/config.php" ]; then
  DB_PASS=$(openssl rand -hex 16)
  mariadb -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost';
              ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
              GRANT SELECT, INSERT, UPDATE, DELETE ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
  sed -e "s|define('DB_USER', 'root')|define('DB_USER', '$DB_USER')|" \
      -e "s|define('DB_PASS', '')|define('DB_PASS', '$DB_PASS')|" \
      -e "s|define('APP_URL', '[^']*')|define('APP_URL', '$APP_URL')|" \
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
INSERT INTO administradores (nombre, apellido) VALUES ('Admin', 'Inicial');
INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado, activo)
VALUES ('$ADMIN_EMAIL', '$ADMIN_HASH', 'administrador', LAST_INSERT_ID(), 1, 1);
SQL
  echo "First administrator: $ADMIN_EMAIL / $ADMIN_PASS  (shown once, change it after logging in)"
fi

chown -R root:root "$DEST"
chown root:www-data "$DEST/includes/config.php"
chown -R www-data:www-data "$DEST/storage"

# result uploads are up to 10MB per file (Debian default is 2M / 8M)
for dir in /etc/php/*/apache2/conf.d; do
  printf 'upload_max_filesize = 10M\npost_max_size = 64M\n' > "$dir/99-clinica.ini"
done

cat > /etc/apache2/sites-available/clinica-imagen.conf <<EOF
<VirtualHost *:80>
    DocumentRoot $DEST

    <Directory $DEST>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    <DirectoryMatch "^$DEST/(includes|sql|vendor|deploy|storage)(/|\$)">
        Require all denied
    </DirectoryMatch>

    <FilesMatch "^(\..*|composer\.(json|lock))\$">
        Require all denied
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/clinica-imagen-error.log
    CustomLog \${APACHE_LOG_DIR}/clinica-imagen-access.log combined
</VirtualHost>
EOF

a2enmod -q rewrite
a2dissite -q 000-default || true
a2ensite -q clinica-imagen
apache2ctl configtest
systemctl restart apache2

echo "Done: $APP_URL"
