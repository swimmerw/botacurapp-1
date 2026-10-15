#!/bin/bash
# HA jardin interior: humedad alta solo sobre 69,9 % (antes 55 %). La baja sigue en 40 %. 15/10/2026
set -e
F=/home/botacura/homeassistant/config/automations/jardin_alertas_ambiente.yaml
sudo test -f $F || { echo "No existe $F"; exit 1; }
sudo cp -p $F $F.bak.$(date +%Y%m%d%H%M%S)
N=$(sudo grep -cE "above: 55$|h > 55|rango 40-55|humedad 40-55%" $F || true)
[ "$N" -ge 3 ] || { echo "No encontre los valores de 55 esperados ($N)"; exit 1; }
sudo sed -i -e "s/above: 55$/above: 69.9/" -e "s/h > 55 /h > 69.9 /g" \
  -e "s/rango 40-55)/rango 40-69,9)/g" -e "s/humedad 40-55%/humedad 40-69,9%/g" $F
sudo mv $F.bak.* /home/botacura/homeassistant/config/archivo_viejo/ 2>/dev/null || true
sudo grep -nE "69.9|69,9" $F | cut -c1-110
sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config > /tmp/check_ha.txt 2>&1 || true
if grep -qiE "Failed config|Invalid config" /tmp/check_ha.txt; then echo "Error de configuracion, revisar"; grep -iE -A6 "Failed config|Invalid config" /tmp/check_ha.txt | head; exit 1; fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: humedad del jardin avisa sobre 69,9 % (y bajo 40 %). Home Assistant reiniciado."
