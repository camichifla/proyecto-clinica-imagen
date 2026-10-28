#!/usr/bin/env bash
# deploy/operador.sh — interactive operator menu for the Clinica Imagen server (Debian).
# Install once with option 9.2 (or: sudo install -m 755 deploy/operador.sh /usr/local/bin/operador.sh
#   && sudo ln -sf /usr/local/bin/operador.sh /usr/local/bin/operador), then run: sudo operador

REPO_URL=https://github.com/camichifla/proyecto-clinica-imagen.git
INSTALL_PATH=/usr/local/bin/operador.sh
LOG_FILE=/var/log/operador.log
BACKUP_DIR=/var/backups/operador
APP_DIR=/var/www/clinica-imagen
APACHE_ACCESS=/var/log/apache2/clinica-imagen-access.log
APACHE_ERROR=/var/log/apache2/clinica-imagen-error.log
RESPALDO_BIN=/usr/local/sbin/clinica-respaldo
SHARE_DIR=/usr/local/share/clinica-imagen
# Config templates (nftables, sshd, fail2ban): next to this script when run from the repo, else the installed copy.
DEPLOY_DIR=$(dirname "$(readlink -f "$0")")/config
[ -f "$DEPLOY_DIR/nftables.conf" ] || DEPLOY_DIR=$SHARE_DIR

umask 077
[ "$(id -u)" -eq 0 ] || { echo "Este script debe ejecutarse como root (sudo operador)." >&2; exit 1; }
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
touch "$LOG_FILE"

log() {  # log LEVEL message
  printf '%s [%s] [%s@%s] %s\n' "$(date '+%F %T')" "$1" "${SUDO_USER:-root}" "$(tty 2>/dev/null || echo notty)" "$2" >> "$LOG_FILE"
}

pausa() { echo; read -rp "Presione Enter para continuar..." _; }

# Clinic logo, drawn to the left of every menu (50 columns wide).
mapfile -t LOGO <<'EOF'
                 ...::.....:.::..

                              .
                               ---:
                               :-:::::
                                -:::::.:
                                :-:::::: .
                                ::::::::::.    .
 ..                            ::::::::::::.   ..
 :.                             :::::::::::::   ::
::                               .-:::::::::::  .:
::                                 .::::::::::   :
:.                                  :=::::::::.  :
:.                                 -=====::::::  :
:.                                -========:.::  :
-:                                -=========-:. .:
::                               .===========-  ::
 :.         .                    :===========.  ::
 ::          ..                  ===========:  ::
  ::       ..  ::.               ==========-  .:
   ::        :.   ::-++++===:::===========:  .:
    .:     .   ::: :++++++++++++++=======   ..
      ..    .:.   -*++++++++++++++++++=    ..
        .      ::=*++**++++++++++++=:    ..
          ..     .-=********+++=:      .
              .                    .
                  .             ..
EOF

# Arrow-key menu: menu TITLE "N) label"...; sets $op to the chosen N. Typing a digit still selects directly.
menu() {
  local title=$1; shift
  local items=("$@") sel=0 i key n=$#
  while true; do
    clear
    local lineas
    mapfile -t lineas <<< "$title" # a multi-line title (main menu) takes one row per line
    for i in "${!items[@]}"; do
      if [ "$i" -eq "$sel" ]; then lineas+=("$(printf '\e[7m> %s\e[0m' "${items[i]}")"); else lineas+=("  ${items[i]}"); fi
    done
    lineas+=("" "↑/↓ para moverse, Enter para elegir")
    # Logo on the left when the terminal is wide enough; menu alone otherwise.
    if [ "$(tput cols 2>/dev/null || echo 80)" -ge 110 ]; then # 50 logo + 3 gap + the widest menu line
      local filas=$(( ${#LOGO[@]} > ${#lineas[@]} ? ${#LOGO[@]} : ${#lineas[@]} ))
      local desde=$(( (${#LOGO[@]} - ${#lineas[@]}) / 2 )) # menu vertically centered on the logo
      (( desde < 0 )) && desde=0
      for (( i = 0; i < filas; i++ )); do
        local linea=''
        (( i >= desde )) && linea=${lineas[i - desde]}
        printf '%-50s   %s\n' "${LOGO[i]}" "$linea"
      done
    else
      printf '%s\n' "${lineas[@]}"
    fi
    IFS= read -rsn1 key
    case $key in
      $'\e') read -rsn2 -t 0.1 key
             case $key in
               '[A'|'OA') sel=$(( (sel - 1 + n) % n )) ;;
               '[B'|'OB') sel=$(( (sel + 1) % n )) ;;
             esac ;;
      '') op=${items[sel]%%)*}; echo; return ;;
      [0-9]) op=$key; echo; return ;;
    esac
  done
}

ok()   { echo "✔ $1"; log OK "$1"; }
warn() { echo "✘ $1"; log WARN "$1"; }

# Prints a log file, falling back to a journalctl command when the file does not exist
# (Debian 12+ uses journald and ships without /var/log/syslog and auth.log).
ver_log() {  # ver_log FILE LINES FALLBACK...
  local file=$1 lines=$2; shift 2
  if [ -f "$file" ]; then tail -n "$lines" "$file"; else "$@" -n "$lines" --no-pager; fi
}

# ---------- 1) Services ----------
menu_servicios() {
  while true; do
    menu "--- Gestión de Servicios ---" \
      "1) Ver estado de Apache" \
      "2) Reiniciar Apache" \
      "3) Ver estado de SSH" \
      "4) Reiniciar SSH" \
      "5) Monitoreo CPU/RAM/Disco" \
      "0) Volver"
    case $op in
      1) log ACTION "Consulta estado Apache"; systemctl status apache2 --no-pager ;;
      2) systemctl restart apache2 && ok "Apache reiniciado correctamente" || warn "No se pudo reiniciar Apache" ;;
      3) log ACTION "Consulta estado SSH"; systemctl status ssh --no-pager ;;
      4) systemctl restart ssh && ok "SSH reiniciado correctamente" || warn "No se pudo reiniciar SSH" ;;
      5) log ACTION "Consulta de CPU, RAM y DISCO"
         echo "--- CPU ---"; top -bn1 | head -5
         echo "--- RAM ---"; free -h
         echo "--- DISCO ---"; df -h -x tmpfs -x devtmpfs ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- 2) Network ----------
TUNEL_SERVICE=/etc/systemd/system/clinica-tunel.service

# Points the app's links and cookie flags at the given public URL.
configurar_url() {  # configurar_url URL true|false
  sed -i -e "s|define('APP_URL', '[^']*')|define('APP_URL', '$1')|" \
         -e "s|define('APP_HTTPS', [a-z]*)|define('APP_HTTPS', $2)|" "$APP_DIR/includes/config.php"
}

# Publishes the site through a Cloudflare quick tunnel: no open ports, HTTPS by Cloudflare.
# ponytail: quick tunnel URL changes on every restart/reboot; re-run this option, or use a named tunnel with a domain for a fixed URL.
publicar_tunel() {
  if ! command -v cloudflared >/dev/null; then
    local deb; deb=$(mktemp --suffix=.deb)
    if ! curl -fsSL -o "$deb" "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-$(dpkg --print-architecture).deb" \
       || ! dpkg -i "$deb"; then
      rm -f "$deb"; warn "No se pudo instalar cloudflared"; return 1
    fi
    rm -f "$deb"
  fi
  cat > "$TUNEL_SERVICE" <<'EOF'
[Unit]
Description=Cloudflare quick tunnel for Clinica Imagen
After=network-online.target apache2.service
Wants=network-online.target

[Service]
ExecStart=/usr/bin/cloudflared tunnel --no-autoupdate --url http://localhost:80
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF
  local inicio url="" i
  inicio=$(date '+%F %T')
  systemctl daemon-reload && systemctl enable --quiet clinica-tunel && systemctl restart clinica-tunel
  echo "Esperando la URL del túnel..."
  for i in $(seq 1 30); do
    url=$(journalctl -u clinica-tunel --since "$inicio" --no-pager -o cat | grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' | tail -n1)
    [ -n "$url" ] && break
    sleep 1
  done
  [ -n "$url" ] || { warn "No se obtuvo la URL del túnel (ver: journalctl -u clinica-tunel)"; return 1; }
  configurar_url "$url" true
  ok "Sitio publicado en $url"
  echo "Nota: mientras el túnel esté activo, ingresar siempre por esa URL (la cookie de sesión exige HTTPS)."
}

detener_tunel() {
  systemctl disable --now --quiet clinica-tunel 2>/dev/null
  rm -f "$TUNEL_SERVICE"; systemctl daemon-reload
  configurar_url "http://$(hostname -I | awk '{print $1}')" false
  ok "Túnel detenido; el sitio vuelve a la IP local"
}

menu_red() {
  log INFO "Entrando al menú de red"
  while true; do
    menu "--- Gestión de Red ---" \
      "1) Ver IPs" \
      "2) Probar conectividad" \
      "3) Ver puertos abiertos" \
      "4) Estado del firewall (nftables)" \
      "5) Estado de fail2ban" \
      "6) Configurar dominio (hostname y /etc/hosts)" \
      "7) Probar resolución DNS" \
      "0) Volver"
    case $op in
      1) log ACTION "Consulta IP del servidor"; ip -br address ;;
      2) read -rp "Host a probar [google.com]: " host
         host=${host:-google.com}
         log ACTION "Ping de prueba a $host"; ping -c 4 "$host" ;;
      3) log ACTION "Consulta de puertos abiertos"; ss -tulpn ;;
      4) log ACTION "Consulta estado nftables"; nft list ruleset ;;
      5) log ACTION "Consulta estado fail2ban"
         fail2ban-client status && fail2ban-client status sshd ;;
      6) read -rp "Nombre de dominio completo (FQDN, ej. clinica.example.com): " fqdn
         if [[ ! $fqdn =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$ ]]; then
           warn "FQDN inválido: $fqdn"
         else
           short=${fqdn%%.*}
           if hostnamectl set-hostname "$fqdn"; then
             sed -i '/^127\.0\.1\.1[[:space:]]/d' /etc/hosts
             echo "127.0.1.1 $fqdn $short" >> /etc/hosts
             ok "Hostname configurado: $fqdn (el registro A público se crea en el registrador del dominio)"
           else
             warn "No se pudo configurar el hostname"
           fi
         fi ;;
      7) read -rp "Nombre a resolver [$(hostname -f 2>/dev/null || hostname)]: " nombre
         nombre=${nombre:-$(hostname -f 2>/dev/null || hostname)}
         log ACTION "Prueba de resolución DNS de $nombre"
         getent hosts "$nombre" || warn "getent no resolvió $nombre" ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- 3) Processes ----------
menu_procesos() {
  log INFO "Entrando al menú de procesos"
  while true; do
    menu "--- Gestión de Procesos ---" \
      "1) Ver top de procesos" \
      "2) Matar proceso por PID" \
      "0) Volver"
    case $op in
      1) log ACTION "Visualización de procesos activos"; ps aux --sort=-%mem | head -15 ;;
      2) read -rp "PID a eliminar: " pid
         if [[ ! $pid =~ ^[0-9]+$ ]] || [ "$pid" -le 1 ]; then
           warn "PID inválido: $pid"
         elif kill "$pid"; then
           ok "Proceso $pid eliminado"
         else
           warn "No se pudo eliminar proceso $pid"
         fi ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- 4) Backups ----------
respaldar() {  # respaldar PATH NAME
  local dest
  dest="$BACKUP_DIR/$2_$(date +%Y%m%d_%H%M%S).tar.gz"
  if tar -czf "$dest" "$1" 2>/dev/null; then ok "Respaldo de $1 creado en $dest"; else warn "Falló el respaldo de $1"; fi
}

menu_respaldos() {
  log INFO "Entrando al menú de respaldos"
  while true; do
    menu "--- Gestión de Respaldos ---" \
      "1) Respaldar /etc y la aplicación (/var/www)" \
      "2) Respaldar base de datos de la clínica" \
      "3) Subir respaldos al remote configurado (rclone)" \
      "4) Respaldar carpeta personalizada" \
      "5) Respaldar carpeta a Google Drive (rclone)" \
      "6) Listar respaldos" \
      "7) Mostrar clave de cifrado de respaldos" \
      "8) Descifrar un respaldo .gpg" \
      "0) Volver"
    case $op in
      1) "$RESPALDO_BIN" archivos || warn "Falló el respaldo de archivos (¿instaló clinica-respaldo? opción 9.2)" ;;
      2) "$RESPALDO_BIN" db || warn "Falló el respaldo de la base de datos (¿instaló clinica-respaldo? opción 9.2)" ;;
      3) "$RESPALDO_BIN" remoto || warn "Falló el respaldo remoto" ;;
      4) read -rp "Ruta local: " ruta
         if [ -e "$ruta" ]; then respaldar "$ruta" "$(basename "$ruta")"; else warn "La ruta $ruta no existe"; fi ;;
      5) if ! command -v rclone >/dev/null; then
           warn "rclone no está instalado (opción 9.1)"
         elif ! rclone listremotes | grep -qx 'gdrive:'; then
           warn "No existe el remote 'gdrive'. Configúrelo con: rclone config"
         else
           read -rp "Ruta local a subir [$BACKUP_DIR]: " ruta
           ruta=${ruta:-$BACKUP_DIR}
           read -rp "Carpeta destino en Google Drive: " carpeta
           # encrypted before upload (clinica-respaldo subir): the plaintext never reaches Google Drive
           RCLONE_REMOTE=gdrive: "$RESPALDO_BIN" subir "$ruta" "$carpeta" || warn "Falló la subida a Google Drive"
         fi ;;
      6) log ACTION "Listado de respaldos"; ls -lh "$BACKUP_DIR" ;;
      7) log ACTION "Mostrar clave de cifrado de respaldos"; "$RESPALDO_BIN" clave || warn "No se pudo mostrar la clave" ;;
      8) read -rp "Archivo .gpg a descifrar: " ruta; "$RESPALDO_BIN" descifrar "$ruta" || warn "No se pudo descifrar $ruta" ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- 5) Users and groups ----------
menu_usuarios() {
  log INFO "Entrando al menú de usuarios"
  while true; do
    menu "--- Gestión de Usuarios ---" \
      "1) Listar usuarios" \
      "2) Listar grupos" \
      "3) Crear usuario (con grupo)" \
      "4) Eliminar usuario" \
      "5) Agregar usuario a un grupo" \
      "0) Volver"
    case $op in
      1) log ACTION "Listado de usuarios"; awk -F: '$3 >= 1000 && $3 < 65534 {print $1" (UID "$3", home "$6")"}' /etc/passwd ;;
      2) log ACTION "Listado de grupos"; cut -d: -f1 /etc/group | sort ;;
      3) read -rp "Nombre de usuario: " usr
         read -rp "Grupo al que asignar: " grp
         if [[ ! $usr =~ ^[a-z_][a-z0-9_-]*$ || ! $grp =~ ^[a-z_][a-z0-9_-]*$ ]]; then
           warn "Nombre de usuario o grupo inválido"
         elif id "$usr" &>/dev/null; then
           warn "El usuario $usr ya existe"
         else
           getent group "$grp" >/dev/null || groupadd "$grp"
           if adduser --ingroup "$grp" --gecos "" "$usr"; then ok "Usuario $usr creado en grupo $grp"; else warn "No se pudo crear usuario $usr"; fi
         fi ;;
      4) read -rp "Usuario a eliminar: " usr
         if ! id "$usr" &>/dev/null; then
           warn "El usuario $usr no existe"
         elif [ "$(id -u "$usr")" -lt 1000 ] || [ "$usr" = "${SUDO_USER:-}" ]; then
           warn "No se permite eliminar el usuario $usr"
         else
           read -rp "¿Eliminar $usr y su carpeta home? (s/N): " c
           if [ "$c" = s ]; then
             if deluser --remove-home "$usr"; then ok "Usuario $usr eliminado"; else warn "No se pudo eliminar $usr"; fi
           fi
         fi ;;
      5) read -rp "Usuario: " usr
         read -rp "Grupo: " grp
         if usermod -aG "$grp" "$usr"; then ok "Usuario $usr agregado al grupo $grp"; else warn "No se pudo agregar $usr a $grp"; fi ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- 6) Databases ----------
menu_bases() {
  log INFO "Entrando al menú de bases de datos"
  while true; do
    menu "--- Gestión de Bases de Datos (MariaDB) ---" \
      "1) Crear base de datos" \
      "2) Listar bases de datos" \
      "3) Eliminar base de datos" \
      "4) Ver tablas de la clínica" \
      "0) Volver"
    case $op in
      1|3) read -rp "Nombre de la base: " db
         if [[ ! $db =~ ^[A-Za-z0-9_]+$ ]]; then
           warn "Nombre de base inválido"
         elif [ "$op" = 1 ]; then
           if mariadb -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"; then ok "DB $db creada"; else warn "No se pudo crear DB $db"; fi
         elif [ "$db" = clinica_imagen ] || [ "$db" = mysql ]; then
           warn "No se permite eliminar la base $db desde el operador"
         else
           read -rp "¿Eliminar la base $db? (s/N): " c
           if [ "$c" = s ]; then
             if mariadb -e "DROP DATABASE \`$db\`"; then ok "DB $db eliminada"; else warn "No se pudo eliminar DB $db"; fi
           fi
         fi ;;
      2) log ACTION "Listado de DBs"; mariadb -e "SHOW DATABASES" ;;
      4) log ACTION "Listado de tablas de clinica_imagen"; mariadb -e "SHOW TABLES" clinica_imagen ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- 7) System logs ----------
menu_registros() {
  log INFO "Entrando al menú de logs del sistema"
  while true; do
    menu "--- Registros del Sistema ---" \
      "1) Últimas 20 líneas de syslog" \
      "2) Últimos intentos SSH (auth.log)" \
      "3) Logs de Apache access.log" \
      "4) Logs de Apache error.log" \
      "5) Ver 4 logs principales" \
      "0) Volver"
    case $op in
      1) log ACTION "Consulta syslog"; ver_log /var/log/syslog 20 journalctl ;;
      2) log ACTION "Consulta intentos SSH"; ver_log /var/log/auth.log 20 journalctl -u ssh ;;
      3) log ACTION "Consulta Apache access.log"; tail -n 20 "$APACHE_ACCESS" ;;
      4) log ACTION "Consulta Apache error.log"; tail -n 20 "$APACHE_ERROR" ;;
      5) log ACTION "Consulta de 4 logs principales"
         echo "--- syslog ---";           ver_log /var/log/syslog 10 journalctl
         echo "--- auth.log ---";         ver_log /var/log/auth.log 10 journalctl -u ssh
         echo "--- apache error.log ---"; tail -n 10 "$APACHE_ERROR"
         echo "--- dmesg ---";            dmesg | tail -n 10 ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- 8) Script log ----------
menu_log_interno() {
  log INFO "Acceso al menú de log interno"
  while true; do
    menu "--- Log Interno del Script ---" \
      "1) Ver últimos 30 eventos" \
      "2) Borrar log interno" \
      "0) Volver"
    case $op in
      1) tail -n 30 "$LOG_FILE" ;;
      2) read -rp "¿Borrar el log interno? (s/N): " c
         [ "$c" = s ] && : > "$LOG_FILE" && log OK "Log interno borrado" && echo "✔ Log interno borrado" ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- 9) Setup ----------
instalar_dependencias() {
  echo "ℹ Instalando dependencias del sistema..."
  export DEBIAN_FRONTEND=noninteractive
  apt-get update && apt-get install -y apache2 mariadb-server rclone gnupg nftables fail2ban python3-systemd openssh-server \
    curl wget git nano rsync iproute2 procps || { warn "Falló la instalación de dependencias"; return 1; }
  systemctl enable --now apache2 mariadb fail2ban ssh
  ok "Dependencias y servicios instalados"
}

# Installs a config template from $DEPLOY_DIR; fails if it is missing.
plantilla() {  # plantilla SRC DEST MODE
  [ -f "$DEPLOY_DIR/$1" ] || { warn "No se encontró $DEPLOY_DIR/$1"; return 1; }
  install -D -m "$3" "$DEPLOY_DIR/$1" "$2"
}

configurar_firewall() {
  plantilla nftables.conf /etc/nftables.conf 644 || return 1
  # nftables replaces ufw: two firewalls managing rules would conflict
  if dpkg -s ufw &>/dev/null; then
    ufw --force disable &>/dev/null; apt-get purge -y ufw &>/dev/null
  fi
  if ! nft -c -f /etc/nftables.conf; then warn "nftables.conf inválido; no se aplicó"; return 1; fi
  systemctl enable --now nftables && systemctl reload nftables && ok "Firewall nftables configurado correctamente"
}

configurar_ssh() {
  plantilla sshd-clinica.conf /etc/ssh/sshd_config.d/clinica.conf 644 || return 1
  if sshd -t; then
    systemctl reload ssh && ok "SSH endurecido (/etc/ssh/sshd_config.d/clinica.conf)"
  else
    rm -f /etc/ssh/sshd_config.d/clinica.conf
    warn "Configuración SSH inválida; se quitó el drop-in"
    return 1
  fi
}

configurar_fail2ban() {
  plantilla fail2ban-clinica.local /etc/fail2ban/jail.d/clinica.local 644 || return 1
  # the apache-auth jail needs its log file to exist before fail2ban starts
  [ -f "$APACHE_ERROR" ] || touch "$APACHE_ERROR"
  systemctl restart fail2ban && ok "fail2ban configurado (jails sshd y apache-auth)" || warn "fail2ban no pudo arrancar"
}

instalar_script() {
  local src
  src=$(readlink -f "$1")
  if [ "$src" != "$(readlink -f "$INSTALL_PATH")" ]; then
    install -m 755 "$src" "$INSTALL_PATH"
  fi
  ln -sf "$INSTALL_PATH" /usr/local/bin/operador
  # keep the config templates so options 9.1 / 9.3 work without the repo
  mkdir -p "$SHARE_DIR"
  cp -f "$(dirname "$src")"/config/{nftables.conf,sshd-clinica.conf,fail2ban-clinica.local} "$SHARE_DIR/" 2>/dev/null
  ok "Script instalado en $INSTALL_PATH (comando: sudo operador)"
}

# Uruguayan CI: 7 digits + check digit (weights 2987634), so the generated accounts pass the app's validation.
ci_valida() {
  local base i suma=0 pesos=(2 9 8 7 6 3 4)
  base=$(printf '%07d' "$1")
  for i in {0..6}; do suma=$(( suma + pesos[i] * ${base:i:1} )); done
  echo "$base$(( (10 - suma % 10) % 10 ))"
}

# 20 test accounts per role (rol01..rol20@clinica-imagen.local), all with one random password shown at the end.
generar_cuentas() {
  local db=clinica_imagen dominio=clinica-imagen.local
  if [ -n "$(mariadb -N "$db" -e "SELECT 1 FROM cuentas WHERE email = 'paciente01@$dominio'" 2>/dev/null)" ]; then
    warn "Las cuentas de prueba ya existen (paciente01@$dominio)"; return 1
  fi
  local nombres=(Lucía Mateo Sofía Santiago Valentina Joaquín Martina Benjamín Camila Facundo Agustina Nicolás
                 Florencia Tomás Julieta Bruno Micaela Diego Carolina Gonzalo Paula Federico Ana Martín Rocío Andrés)
  local apellidos=(González Rodríguez Fernández López Martínez Pérez García Sosa Silva Pereira Díaz Álvarez Romero
                   Suárez Benítez Acosta Méndez Cabrera Núñez Olivera Castro Ramos Vázquez Correa Torres Viera)
  local calles=("Av. Italia" "Bv. Artigas" "Av. 18 de Julio" "Av. Rivera" "Colonia" "Canelones" "Av. Brasil" "Durazno")
  local -A tablas=([paciente]=pacientes [medico]=medicos [profesional]=profesionales [administrador]=administradores [recepcionista]=recepcionistas)
  local clave hash sql rol r=0 i d nombre apellido ci desde
  clave=$(openssl rand -hex 6)
  hash=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$clave")
  sql="START TRANSACTION; SET @hash = '$hash';"
  for rol in paciente medico profesional administrador recepcionista; do
    r=$(( r + 1 ))
    for i in {1..20}; do
      nombre=${nombres[RANDOM % ${#nombres[@]}]}; apellido=${apellidos[RANDOM % ${#apellidos[@]}]}
      ci=$(ci_valida $(( 5000000 + r * 1000 + i )))
      case $rol in
        paciente)      sql+="INSERT INTO pacientes (nombre, apellido, ci, direccion, numero) VALUES ('$nombre', '$apellido', '$ci', '${calles[RANDOM % ${#calles[@]}]} $(( 100 + RANDOM % 4000 ))', '09$(( 1000000 + RANDOM % 9000000 ))');" ;;
        recepcionista) sql+="INSERT INTO recepcionistas (nombre, apellido, ci, sucursal_id) VALUES ('$nombre', '$apellido', '$ci', (SELECT id FROM sucursales WHERE activo = 1 ORDER BY RAND() LIMIT 1));" ;;
        *)             sql+="INSERT INTO ${tablas[$rol]} (nombre, apellido, ci) VALUES ('$nombre', '$apellido', '$ci');" ;;
      esac
      sql+="SET @ref = LAST_INSERT_ID();"
      sql+="INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado) VALUES ('$rol$(printf '%02d' "$i")@$dominio', @hash, '$rol', @ref, 1);"
      if [ "$rol" = profesional ]; then
        # 1-3 random active studies and sedes; one shift on 3-5 random weekdays (start 8-12 h, 4-6 h long).
        sql+="INSERT INTO profesional_especializacion (profesional_id, especializacion) SELECT @ref, clave FROM estudios WHERE activo = 1 ORDER BY RAND() LIMIT $(( 1 + RANDOM % 3 ));"
        sql+="INSERT INTO profesional_sucursal (profesional_id, sucursal_id) SELECT @ref, id FROM sucursales WHERE activo = 1 ORDER BY RAND() LIMIT $(( 1 + RANDOM % 3 ));"
        for d in $(shuf -i 1-6 -n $(( 3 + RANDOM % 3 ))); do
          desde=$(( 8 + RANDOM % 5 ))
          sql+="INSERT INTO profesional_horarios (profesional_id, dia_semana, hora_apertura, hora_cierre) VALUES (@ref, $d, '$desde:00', '$(( desde + 4 + RANDOM % 3 )):00');"
        done
      fi
    done
  done
  sql+="COMMIT;"
  # One session: any error aborts and the open transaction is rolled back, so it is all or nothing.
  if mariadb --default-character-set=utf8mb4 "$db" -e "$sql"; then
    ok "100 cuentas generadas (20 por rol)"
    echo "Correos: paciente01..20, medico01..20, profesional01..20, administrador01..20, recepcionista01..20 @$dominio"
    echo "Contraseña de todas: $clave"
  else
    warn "No se pudieron generar las cuentas"
  fi
}

menu_configuracion() {
  log INFO "Entrando al menú de configuración"
  while true; do
    menu "--- Extras ---" \
      "1) Configurar manualmente el sistema" \
      "2) Instalar automáticamente desde GitHub" \
      "3) Actualizar script desde GitHub" \
      "4) Publicar en internet (Cloudflare Tunnel, URL temporal)" \
      "5) Dejar de publicar en internet" \
      "6) Generar Cuentas" \
      "0) Volver"
    case $op in
      1) instalar_dependencias && configurar_firewall && configurar_ssh && configurar_fail2ban && ok "Configuración manual completada" ;;
      2) echo "ℹ Descargando instalador automático desde GitHub..."
         read -rp "Dominio para HTTPS (vacío = solo HTTP): " DOMAIN
         if [ -n "$DOMAIN" ]; then read -rp "Email de contacto para Let's Encrypt: " CERTBOT_EMAIL; fi
         read -rp "¿Borrar la base de datos y empezar de cero? Se pierden TODOS los pacientes, citas y resultados (s/N): " c
         if [[ $c =~ ^[sS]$ ]]; then RESET_DB=1; log ACTION "Reinstalación con reseteo de base"; else RESET_DB=0; fi
         CRED_FILE=$(mktemp) # debian-setup.sh writes the new admin's credentials here (only when the DB is created)
         export DOMAIN CERTBOT_EMAIL RESET_DB CRED_FILE
         instdir=$(mktemp -d)
         # the operator runs with umask 077; the clone becomes Apache's document root
         if instalar_dependencias && (umask 022; git clone --depth 1 "$REPO_URL" "$instdir"); then
           # debian-setup.sh expects to run from the project root
           (umask 022; cd "$instdir" && bash deploy/debian-setup.sh) && ok "Aplicación desplegada en $APP_DIR"
           DEPLOY_DIR=$instdir/deploy/config
           configurar_firewall; configurar_ssh; configurar_fail2ban
           instalar_script "$instdir/deploy/operador.sh"
         else
           warn "Falló la instalación automática"
         fi
         if [ -s "$CRED_FILE" ]; then
           echo; echo "==============================================="; cat "$CRED_FILE"; echo "==============================================="
         fi
         rm -f "$CRED_FILE" ;;
      3) rm -rf /tmp/operador_update
         if git clone --depth 1 "$REPO_URL" /tmp/operador_update; then
           instalar_script /tmp/operador_update/deploy/operador.sh
           echo "Reinicie el operador para usar la nueva versión."
         else
           warn "No se pudo descargar la actualización"
         fi ;;
      4) publicar_tunel ;;
      5) detener_tunel ;;
      6) generar_cuentas ;;
      0) return ;;
      *) echo "Opción inválida" ;;
    esac
    pausa
  done
}

# ---------- Main menu ----------
log INFO "Inicio del operador"
while true; do
  menu $'==============================================\n   OPERADOR DE CENTRO DE CÓMPUTOS - CLÍNICA IMAGEN\n==============================================' \
    "1) Administración de sistemas" \
    "2) Gestión de redes" \
    "3) Gestión de procesos" \
    "4) Gestión de respaldos" \
    "5) Gestión de usuarios y grupos" \
    "6) Gestión de bases de datos" \
    "7) Monitoreo y registros del sistema" \
    "8) Log interno del script" \
    "9) Extras" \
    "0) Salir"
  case $op in
    1) menu_servicios ;;
    2) menu_red ;;
    3) menu_procesos ;;
    4) menu_respaldos ;;
    5) menu_usuarios ;;
    6) menu_bases ;;
    7) menu_registros ;;
    8) menu_log_interno ;;
    9) menu_configuracion ;;
    0) log INFO "Salida del operador"; exit 0 ;;
    *) echo "Opción inválida"; pausa ;;
  esac
done
