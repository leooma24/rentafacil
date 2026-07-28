#!/usr/bin/env bash
#
# Respaldo diario de RentaFácil.
#
# Instalación en el VPS:
#   sudo cp /var/www/rentafacil/deploy/backup-rentafacil.sh /usr/local/bin/
#   sudo chmod 700 /usr/local/bin/backup-rentafacil.sh
#   sudo crontab -e
#   # Agregar:  20 3 * * * /usr/local/bin/backup-rentafacil.sh >> /var/log/rentafacil-backup.log 2>&1
#
# A las 3:20 y no a las 3:00: a esa hora corre el respaldo de LavadoFácil, que
# es otro proyecto en el mismo servidor, y no conviene que los dos mysqldump se
# encimen.
#
# Qué agrega esto sobre lo que ya había:
#
# La app ya corría `backup:run --only-db` todos los días a las 2am con
# spatie/laravel-backup. Aquello no comprueba que el volcado sirva, no guarda
# los archivos subidos y no deja copias mensuales. Esto sí las tres cosas.
#
# OJO: los dos quedan en el MISMO disco. Sirven contra un borrado o una
# migración mala, que es lo común, pero no contra la pérdida del servidor. Para
# eso hace falta copiarlos fuera, y eso todavía no está.

set -euo pipefail

APP_DIR="/var/www/rentafacil"
BACKUP_DIR="/var/backups/rentafacil"
RETENTION_DAYS=14
MENSUALES_DIR="$BACKUP_DIR/mensuales"
DATE=$(date +%Y-%m-%d)

# Las tablas que tienen que aparecer en el volcado. Si falta alguna, el
# respaldo salió mal aunque el archivo exista: un .gz de 20 bytes se ve igual
# de tranquilo que uno bueno hasta el día que hay que restaurarlo.
TABLAS_ESPERADAS=(companies users customers rentals payments washing_machines)

fallar() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
    exit 1
}

[ -f "$APP_DIR/.env" ] || fallar "no encuentro $APP_DIR/.env"

# Lee una variable del .env quitando sólo las comillas que la envuelven, no
# todas: una contraseña con comillas adentro quedaría corrupta.
leer_env() {
    grep -E "^${1}=" "$APP_DIR/.env" \
        | head -1 \
        | cut -d'=' -f2- \
        | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

DB_USER=$(leer_env DB_USERNAME)
DB_PASS=$(leer_env DB_PASSWORD)
DB_NAME=$(leer_env DB_DATABASE)

[ -n "$DB_NAME" ] || fallar "DB_DATABASE viene vacío en el .env"

# Guardia: este script es de RentaFácil y de nadie más. Si algún día alguien
# copia el archivo y se le olvida cambiar la ruta, mejor que truene aquí a que
# se ponga a volcar la base de otro proyecto.
case "$DB_NAME" in
    lavadofacil*|tenant_*|docfacil|futbolpro|services|todo)
        fallar "DB_DATABASE es '$DB_NAME', que es de otro proyecto. Abortando."
        ;;
esac

mkdir -p "$BACKUP_DIR" "$MENSUALES_DIR"
chmod 700 "$BACKUP_DIR"

# La contraseña va en un archivo temporal y no en la línea de comandos: con
# -p"$DB_PASS" cualquiera con un `ps` en el momento justo la ve.
CREDENCIALES=$(mktemp)
chmod 600 "$CREDENCIALES"
trap 'rm -f "$CREDENCIALES"' EXIT

cat > "$CREDENCIALES" <<EOF
[client]
user=$DB_USER
password=$DB_PASS
EOF

DESTINO="$BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz"

# --single-transaction para no bloquear la app mientras corre.
mysqldump --defaults-extra-file="$CREDENCIALES" \
    --single-transaction --quick --no-tablespaces \
    "$DB_NAME" | gzip > "$DESTINO"

# --- Comprobar que el respaldo sirve, no sólo que existe ---

gzip -t "$DESTINO" 2>/dev/null || fallar "el archivo salió corrupto: $DESTINO"

TAMANO=$(stat -c%s "$DESTINO")
[ "$TAMANO" -gt 10240 ] || fallar "el respaldo pesa $TAMANO bytes, algo salió mal"

# Se saca primero la lista de tablas y luego se busca en ella, en vez de
# rastrear el volcado entero una vez por tabla.
#
# Nada de `zcat | grep -q` aquí: grep -q cierra la tubería en cuanto encuentra
# la primera coincidencia, el proceso de la izquierda recibe SIGPIPE y con
# `set -o pipefail` eso cuenta como fallo. Sólo se nota cuando el volcado es
# grande; con uno chico cabe en el búfer y pasa desapercibido.
TABLAS_EN_RESPALDO=$(zcat "$DESTINO" | grep -o 'CREATE TABLE `[^`]*`' | tr -d '`' | sed 's/^CREATE TABLE //')

for tabla in "${TABLAS_ESPERADAS[@]}"; do
    case $'\n'"$TABLAS_EN_RESPALDO"$'\n' in
        *$'\n'"$tabla"$'\n'*) ;;
        *) fallar "al respaldo le falta la tabla '${tabla}'" ;;
    esac
done

CUANTAS_TABLAS=$(printf '%s\n' "$TABLAS_EN_RESPALDO" | wc -l)

# Cuántos renglones quedaron guardados, para verlo en el log de un vistazo.
FILAS=$(mysql --defaults-extra-file="$CREDENCIALES" -N -B -e \
    "SELECT COUNT(*) FROM companies" "$DB_NAME" 2>/dev/null || echo '?')

# --- Archivos subidos (fotos de incidencias, logos) ---
#
# La app ya guarda sus propios respaldos de base con spatie/laravel-backup en
# storage/app/<nombre de la app>/, y esa carpeta pesa 57 MB. Sin excluirla, esto
# estaría respaldando respaldos todos los días: 800 MB en dos semanas, sobre un
# disco que va al 74%.

if [ -d "$APP_DIR/storage/app" ]; then
    tar -czf "$BACKUP_DIR/storage_${DATE}.tar.gz" \
        --exclude='storage/app/Renta Facil - Lavadoras' \
        --exclude='storage/app/backup-temp' \
        -C "$APP_DIR" storage/app
fi

# --- Rotación ---

# El primero de cada mes se guarda aparte y no entra a la rotación: si un daño
# pasa desapercibido más de dos semanas, los diarios ya no alcanzan.
if [ "$(date +%d)" = "01" ]; then
    cp "$DESTINO" "$MENSUALES_DIR/"
fi

find "$BACKUP_DIR" -maxdepth 1 -type f -name "*.gz" -mtime +${RETENTION_DAYS} -delete
find "$MENSUALES_DIR" -type f -name "*.gz" -mtime +180 -delete

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Respaldo OK: $DESTINO ($(du -h "$DESTINO" | cut -f1), ${CUANTAS_TABLAS} tablas, ${FILAS} empresas)"
