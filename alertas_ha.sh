#!/bin/bash
# Alertas de Home Assistant por WhatsApp con plantilla aprobada (13/10/2026). Correr en la Raspberry.
set -e
C=/home/botacura/homeassistant/config
TS=$(date +%Y%m%d%H%M)
TOKEN=$(sudo grep -E '^whatsapp_ha_token:' $C/secrets.yaml | sed -E 's/^whatsapp_ha_token:\s*//; s/^"//; s/"$//; s/^Bearer //')
EST=$(curl -s -H "Authorization: Bearer $TOKEN" "https://graph.facebook.com/v21.0/27846116655070945/message_templates?name=alerta_botacura&fields=name,status" | python3 -c 'import sys,json; d=json.load(sys.stdin).get("data",[]); print(d[0]["status"] if d else "NO_EXISTE")')
echo "Plantilla alerta_botacura: $EST"
if [ "$EST" != "APPROVED" ]; then echo "Todavia no esta aprobada. No se cambio nada; vuelve a correrlo mas tarde."; exit 1; fi

sudo cp $C/configuration.yaml $C/configuration.yaml.bak.$TS
sudo python3 - "$C/configuration.yaml" <<'PY'
import sys, re
f = sys.argv[1]
s = open(f, encoding='utf-8').read()
n = 0
for num in ('56961910398', '56940014282'):
    viejo = '{{ {"messaging_product": "whatsapp", "to": "%s", "type": "text", "text": {"body": mensaje}} | tojson }}' % num
    nuevo = ('{{ {"messaging_product": "whatsapp", "to": "%s", "type": "template", "template": {"name": "alerta_botacura", '
             '"language": {"code": "es"}, "components": [{"type": "body", "parameters": [{"type": "text", '
             '"text": (mensaje | replace("\\n", " "))[:900]}]}]}} | tojson }}') % num
    if viejo in s:
        s = s.replace(viejo, nuevo); n += 1
if n != 2:
    sys.exit('ERROR: se esperaban 2 alertas y se encontraron %d. No se cambio nada.' % n)
open(f, 'w', encoding='utf-8').write(s)
print('Alertas cambiadas a plantilla: 2')
PY

# Recuperar las alertas de la piscina (quedaron en archivo_viejo y por eso aparecen "no disponibles")
sudo docker exec homeassistant python3 - <<'PY'
import yaml, os
src = yaml.safe_load(open('/config/archivo_viejo/automations.yaml', encoding='utf-8')) or []
hechas = 0
for a in src:
    if str(a.get('alias', '')).startswith('Alerta Piscina'):
        nombre = '/config/automations/piscina_' + ('baja' if 'bajo' in a['alias'] else 'alta') + '.yaml'
        acciones = a.get('actions') or []
        if not any(x.get('action') == 'rest_command.whatsapp_alerta_2' for x in acciones):
            msj = acciones[0]['data']['mensaje'] if acciones else ''
            acciones.append({'action': 'rest_command.whatsapp_alerta_2', 'data': {'mensaje': msj}})
            a['actions'] = acciones
        if not os.path.exists(nombre):
            yaml.safe_dump(a, open(nombre, 'w', encoding='utf-8'), allow_unicode=True, sort_keys=False)
            hechas += 1
print('Alertas de piscina recuperadas:', hechas)
PY

echo "Revisando configuracion..."
if ! sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config 2>&1 | tee /tmp/check_ha.txt | grep -qiE "^Testing configuration|Successful config"; then :; fi
if grep -qiE "invalid|error" /tmp/check_ha.txt; then
  echo "La configuracion tiene errores; restaurando respaldo:"; grep -iE "invalid|error" /tmp/check_ha.txt | head -20
  sudo cp $C/configuration.yaml.bak.$TS $C/configuration.yaml
  sudo rm -f $C/automations/piscina_baja.yaml $C/automations/piscina_alta.yaml
  exit 1
fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: Home Assistant reiniciado (tarda 1 a 2 minutos en volver)"
