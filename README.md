# Clínica Imagen

Sistema web de una clínica de diagnóstico por imágenes: PHP + MariaDB, páginas `.html` que consumen una API JSON (`api/`). Este documento es también el manual de instalación en servidor.

## Requisitos

- Debian 12 o 13 (VM o servidor), acceso `sudo` y salida a Internet.
- Para HTTPS: un dominio propio y el puerto 80/443 accesibles desde Internet.
- Opcional: cuenta de Google Drive para los respaldos remotos (`rclone config`).

## Instalación rápida

```bash
git clone https://github.com/camichifla/proyecto-clinica-imagen.git
cd proyecto-clinica-imagen
sudo bash deploy/operador.sh      # menú 9 -> 2) Instalar automáticamente desde GitHub
```

La opción 9.2 pide el dominio (vacío = solo HTTP) y el email de Let's Encrypt, instala dependencias, despliega la app y configura firewall, SSH y fail2ban. Luego el menú queda disponible con `sudo operador`. También se puede desplegar solo la app:

```bash
sudo bash deploy/debian-setup.sh                                   # HTTP
DOMAIN=clinica.example.com CERTBOT_EMAIL=you@example.com sudo -E bash deploy/debian-setup.sh   # HTTPS
```

Al terminar se muestra una sola vez el usuario administrador inicial (`admin@clinica-imagen.local` y una clave aleatoria). Es seguro volver a ejecutarlo: actualiza el código sin tocar la base, `config.php` ni los resultados subidos.

## Qué configura cada paso

| Componente | Archivo | Qué hace |
|---|---|---|
| Apache | `deploy/debian-setup.sh` | VirtualHost en `/var/www/clinica-imagen`, `mod_rewrite`/`headers`, bloquea `includes`, `sql`, `deploy`, `storage`; logs `clinica-imagen-{access,error}.log`; PHP con subidas de hasta 10 MB. |
| MariaDB | `deploy/debian-setup.sh` | Crea `clinica_imagen`, importa `sql/schema.sql` y un usuario `clinica` con mínimo privilegio (solo `SELECT, INSERT, UPDATE, DELETE` sobre esa base) y clave aleatoria en `includes/config.php` (modo 640). |
| nftables | `deploy/config/nftables.conf` | Reemplaza a ufw. Política `drop` de entrada; permite loopback, conexiones establecidas, ICMP/ICMPv6 limitado, SSH (22) con límite por IP, y 80/443. |
| SSH | `deploy/config/sshd-clinica.conf` | Drop-in en `/etc/ssh/sshd_config.d/`: sin root, 3 intentos, 30 s de gracia, sin X11, keepalive. Se valida con `sshd -t` antes de recargar. `AllowGroups` y `PasswordAuthentication no` quedan para activar manualmente cuando haya claves SSH. |
| fail2ban | `deploy/config/fail2ban-clinica.local` | Jails `sshd` y `apache-auth` (log de errores de la app); bloqueos vía nftables, 1 h tras 5 fallos en 10 min. |
| Respaldos | `deploy/respaldo.sh`, `deploy/config/cron-clinica-imagen` | Script `/usr/local/sbin/clinica-respaldo` y cron en `/etc/cron.d/` (ver abajo). |
| Entorno | `deploy/config/profile-clinica.sh` | `/etc/profile.d/clinica-imagen.sh`: variables `CLINICA_APP_DIR`, `CLINICA_BACKUP_DIR`, `CLINICA_LOG` y alias (`operador`, `clinica`, `clinica-logs`, `clinica-respaldo`, `ll`). |
| HTTPS | `deploy/debian-setup.sh` | Con `DOMAIN` y `CERTBOT_EMAIL`, instala certbot, emite el certificado y redirige a HTTPS (`APP_HTTPS=true`). |

### Dominio y DNS

El registro **A** público (`clinica.example.com -> IP del servidor`) se crea en el panel del registrador del dominio; debe existir y propagarse antes de ejecutar certbot, que valida el dominio por el puerto 80. En el servidor, el menú `2) Gestión de redes` permite fijar el hostname y `/etc/hosts` (opción 6) y probar la resolución con `getent` (opción 7).

## Respaldos

`clinica-respaldo` guarda en `/var/backups/operador` (permisos 700) y borra copias locales de más de 14 días. Registra en `/var/log/operador.log`.

| Modo | Cron | Contenido |
|---|---|---|
| `db` | diario 02:00 | `mariadb-dump` de `clinica_imagen`, comprimido |
| `archivos` | domingo 03:00 | `/etc` y `/var/www/clinica-imagen` (incluye `storage/resultados`) |
| `remoto` | diario 04:00 | `rclone copy` a `gdrive:clinica-imagen`; se omite si el remote no existe (`RCLONE_REMOTE` lo cambia) |

Restaurar la base:

```bash
gunzip -c /var/backups/operador/clinica_imagen_YYYYMMDD_HHMMSS.sql.gz | sudo mariadb clinica_imagen
```

## Correo (SMTP)

La instalación deja el SMTP vacío. Sin SMTP:

- Con HTTP, los enlaces de verificación y de recuperación de contraseña se escriben en `storage/mail.log`.
- Con HTTPS (certbot o Cloudflare Tunnel) no se envía nada, para no dejar enlaces en texto plano en el servidor. Los pacientes que se registren no van a poder verificar la cuenta ni iniciar sesión.

Completar los datos en `/var/www/clinica-imagen/includes/config.php` (reinstalar no pisa este archivo). Ejemplo con Gmail, que requiere verificación en dos pasos y una [contraseña de aplicación](https://myaccount.google.com/apppasswords):

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'cuenta@gmail.com');
define('SMTP_PASS', 'contraseña-de-aplicación');
define('SMTP_FROM', 'cuenta@gmail.com'); // Gmail exige que coincida con SMTP_USER
```

Los errores de envío quedan en `/var/log/apache2/clinica-imagen-error.log` (líneas `enviar_correo:`).

## Publicar en internet (Cloudflare Tunnel)

Menú `2) Gestión de redes`, opción 8. Sin cuenta, dominio ni puertos abiertos: instala `cloudflared`, lo deja como servicio (`clinica-tunel`) y publica el sitio en una URL `https://…trycloudflare.com` con HTTPS de Cloudflare. El operador actualiza `APP_URL` y `APP_HTTPS=true` en `includes/config.php`.

- La URL cambia cada vez que el servicio o el servidor se reinician: volver a ejecutar la opción 8 para obtener la nueva.
- Mientras esté activo, ingresar siempre por esa URL (la cookie de sesión exige HTTPS; por la IP local no se puede iniciar sesión).
- Apache toma la IP real del visitante del header `CF-Connecting-IP` (`mod_remoteip`, solo confiado desde localhost), así el límite de intentos y el log de eventos siguen funcionando por IP.
- La opción 9 detiene el túnel y vuelve `APP_URL` a la IP local.

## Desarrollo local (XAMPP)

1. Copiar el proyecto a `htdocs` e iniciar Apache y MySQL.
2. `cp includes/config.sample.php includes/config.php` y ajustar `DB_USER`, `DB_PASS`, `APP_URL`.
3. Crear la base `clinica_imagen` e importar `sql/schema.sql`.

## Estructura

```
api/        endpoints JSON
assets/     JS, CSS e imágenes
deploy/     operador.sh, debian-setup.sh, respaldo.sh; config/ con plantillas (nftables, ssh, fail2ban, cron, profile)
docs/       documentación
includes/   lógica PHP compartida (config, db, sesión, mailer, validación)
sql/        schema.sql (esquema completo + datos iniciales)
storage/    resultados subidos (fuera de acceso web)
views/      páginas .html
```
