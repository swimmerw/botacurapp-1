#!/bin/bash
# Home Assistant: cambia la plantilla de WhatsApp de las alertas (alerta_botacura -> la nueva). 15/10/2026
# Uso: ... | bash -s <nombre_plantilla_nueva>
set -e
N="$1"; [ -n "$N" ] || { echo "Falta el nombre de la plantilla nueva"; exit 1; }
C=/home/botacura/homeassistant/config
F=$(sudo grep -rlE "alerta_botacura" $C --include=*.yaml --exclude-dir=archivo_viejo --exclude-dir=.storage || true)
[ -n "$F" ] || { echo "No encontre 'alerta_botacura' en la configuracion"; exit 1; }
T=$(date +%Y%m%d%H%M%S)
for f in $F; do sudo cp -p "$f" "$f.bak.$T"; sudo sed -i "s/alerta_botacura/$N/g" "$f"; echo "Cambiado: $f"; done
sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config > /tmp/check_ha.txt 2>&1 || true
if grep -qiE "Failed config|Invalid config" /tmp/check_ha.txt; then
  echo "Error de configuracion; se restaura"; for f in $F; do sudo cp -p "$f.bak.$T" "$f"; done; exit 1
fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: alertas usando la plantilla $N. Home Assistant reiniciado."
