# /etc/profile.d/clinica-imagen.sh — variables de entorno y alias para el servidor Clinica Imagen.
# Se carga automáticamente en cada sesión de login.

export CLINICA_APP_DIR=/var/www/clinica-imagen
export CLINICA_BACKUP_DIR=/var/backups/operador
export CLINICA_LOG=/var/log/operador.log

alias operador='sudo operador'
alias clinica-logs='sudo tail -f /var/log/apache2/clinica-imagen-error.log'
alias clinica-respaldo='sudo clinica-respaldo db'
alias ll='ls -alF'
alias clinica='cd $CLINICA_APP_DIR'
