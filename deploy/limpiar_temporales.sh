#!/bin/bash
#
# Limpieza semanal del VPS. Es un script del SERVIDOR, no de RentaFácil: le
# sirve a todos los proyectos que viven ahí. Se guarda aquí para que quede
# versionado en algún lado, porque antes sólo existía en /usr/local/bin.
#
# Instalación:
#   sudo cp deploy/limpiar_temporales.sh /usr/local/bin/
#   sudo chmod 700 /usr/local/bin/limpiar_temporales.sh
#   # cron (domingos 3am):
#   0 3 * * 0 /usr/local/bin/limpiar_temporales.sh >> /var/log/limpieza.log 2>&1
#
# POR QUÉ SE REESCRIBIÓ:
#
# La versión anterior tenía finales de línea CRLF y codificación ISO-8859. El
# shebang quedaba como "/bin/bash\r", que no existe, así que el script fallaba
# al arrancar todas las semanas sin que nadie lo viera: 48 correos de cron sin
# leer. Por eso la caché de snap llegó a 4.5 GB, la de apt a 120 MB y el
# journal a 200 MB.
#
# Guardar este archivo SIEMPRE con finales de línea LF.

set -uo pipefail

echo "=== Limpieza $(date '+%Y-%m-%d %H:%M:%S') ==="

# --- Temporales del sistema ---
#
# Por edad y no a lo bruto: "rm -rf /tmp/*" con los servicios encendidos se
# lleva sockets y archivos de sesión en uso. Nunca llegó a correr, así que
# tampoco es que se echara de menos.
find /tmp -type f -atime +7 -delete 2>/dev/null
find /var/tmp -type f -atime +7 -delete 2>/dev/null

# --- Paquetes ---
if command -v apt-get &> /dev/null; then
    apt-get clean -y > /dev/null 2>&1
    apt-get autoclean -y > /dev/null 2>&1
    echo "apt: caché limpia"
fi

# --- Caché de descargas de snap ---
#
# snapd no la limpia solo. Llegó a 4.5 GB de paquetes ya instalados: son
# descargas, se vuelven a bajar si algún día hacen falta.
if [ -d /var/lib/snapd/cache ]; then
    ANTES=$(du -sm /var/lib/snapd/cache 2>/dev/null | cut -f1)
    rm -rf /var/lib/snapd/cache/* 2>/dev/null
    echo "snap: caché de descargas liberada (${ANTES:-0} MB)"
fi

# --- Revisiones viejas de snap ---
#
# Al actualizar, snap deja la versión anterior deshabilitada por si hay que
# volver atrás. Con 10 paquetes eso son varios GB parados.
if command -v snap &> /dev/null; then
    snap list --all 2>/dev/null | awk '/deshabilitado|disabled/{print $1, $3}' > /tmp/snaps_viejos.txt
    while read -r nombre revision; do
        [ -n "$nombre" ] || continue
        snap remove "$nombre" --revision="$revision" > /dev/null 2>&1 \
            && echo "snap: quitada revisión vieja de $nombre ($revision)"
    done < /tmp/snaps_viejos.txt
    rm -f /tmp/snaps_viejos.txt
fi

# --- Registros ---
#
# Sólo los que ya nadie mira. La versión anterior truncaba TODOS los *.log de
# /var/log, incluidos los que se están escribiendo en ese momento.
find /var/log -type f -name "*.log" -mtime +14 -exec truncate -s 0 {} \; 2>/dev/null
find /var/log -type f -name "*.gz" -mtime +30 -delete 2>/dev/null

if command -v journalctl &> /dev/null; then
    journalctl --vacuum-time=7d 2>&1 | tail -1
fi

# --- Buzones de correo del sistema ---
#
# Un cron que no redirige su salida le manda un correo a root cada vez que
# corre. Uno de torneodefutbolpro dejó 313 mil mensajes y 273 MB.
for buzon in /var/mail/root /var/mail/www-data; do
    if [ -f "$buzon" ] && [ "$(stat -c%s "$buzon")" -gt 10485760 ]; then
        truncate -s 0 "$buzon"
        echo "correo: $buzon vaciado (pasaba de 10 MB)"
    fi
done

echo "Espacio libre: $(df -h / | awk 'NR==2{print $4}') ($(df -h / | awk 'NR==2{print $5}') usado)"
echo "=== Fin ==="
