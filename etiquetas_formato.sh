#!/bin/bash
# HA: pasa las etiquetas a "NOMBRE emoji| texto" y saca palabras repetidas. 15/10/2026
set -e
A=/home/botacura/homeassistant/config/automations
T=$(date +%Y%m%d%H%M%S)
R=/home/botacura/homeassistant/config/archivo_viejo/respaldo_formato_$T.tar.gz
sudo tar czf $R -C /home/botacura/homeassistant/config automations && echo "Respaldo: $R"
sudo sed -i \
  -e "s/🌿 JARDÍN | Jardin interior: /JARDÍN 🌿| /" -e "s/🌿 JARDÍN | /JARDÍN 🌿| /" -e "s/texto: 'Jardin interior: /texto: '/" \
  -e "s/🏊 PISCINA | Alerta Piscina: la temperatura/PISCINA 🏊| temperatura/" -e "s/🏊 PISCINA | /PISCINA 🏊| /" \
  -e "s/🆘 SOS | SOS /SOS 🆘| /" -e "s/🆘 SOS | /SOS 🆘| /" \
  -e "s/🔥 GAS | gas bajo en /GAS 🔥| bajo en /" -e "s/🔥 GAS | /GAS 🔥| /" \
  -e "s/💧 AGUA | /AGUA 💧| /" -e "s/♨️ TINAJAS | /TINAJAS ♨️| /" -e "s/⚙️ SISTEMA | /SISTEMA ⚙️| /" \
  $A/*.yaml
sudo grep -h "mensaje:" $A/*.yaml | sed 's/^ *//' | cut -c1-90
sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config > /tmp/check_ha.txt 2>&1 || true
if grep -qiE "Failed config|Invalid config" /tmp/check_ha.txt; then
  echo "Error de configuracion; se restaura"; sudo rm -rf $A && sudo tar xzf $R -C /home/botacura/homeassistant/config; exit 1
fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: etiquetas con formato NOMBRE emoji| . Home Assistant reiniciado."
