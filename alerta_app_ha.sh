#!/bin/bash
# Home Assistant: recibe avisos de BotacurApp (webhook solo local) y los manda por WhatsApp SOLO al administrador.
# Uso: ... | bash -s <webhook_id>
set -e
ID="$1"
[ -z "$ID" ] && { echo "Falta el identificador (lo entrega el script de AWS)"; exit 1; }
A=/home/botacura/homeassistant/config/automations
F=$A/alerta_desde_botacurapp.yaml
sudo tee $F >/dev/null <<YML
id: alerta_desde_botacurapp
alias: App | Avisos de BotacurApp por WhatsApp (solo administrador)
description: Recibe avisos de la app (por ejemplo, un dia pedido que pasa de 10 personas) y los envia con la plantilla alerta_botacura.
mode: queued
max: 10
triggers:
  - trigger: webhook
    webhook_id: "$ID"
    allowed_methods: [POST]
    local_only: true
conditions: []
actions:
  - action: rest_command.whatsapp_alerta
    data:
      mensaje: "{{ ((trigger.json.mensaje if trigger.json is defined and trigger.json.mensaje is defined else 'Aviso de BotacurApp') | string)[:700] }}"
YML
sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config > /tmp/check_ha.txt 2>&1 || true
if grep -qiE "Failed config|Invalid config" /tmp/check_ha.txt; then
  echo "Configuracion con errores; se quita el archivo nuevo:"; grep -iE -A6 "Failed config|Invalid config" /tmp/check_ha.txt | head -20
  sudo rm -f $F; exit 1
fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: Home Assistant recibe avisos de BotacurApp (reiniciado; espera 1 minuto antes de probar)."
