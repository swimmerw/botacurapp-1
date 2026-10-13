#!/bin/bash
# Recupera las alertas de la piscina desde archivo_viejo (correr en la Raspberry)
set -e
C=/home/botacura/homeassistant/config
sudo docker exec -i homeassistant python3 - <<'PY'
import yaml, os
src = yaml.safe_load(open('/config/archivo_viejo/automations.yaml', encoding='utf-8')) or []
hechas = 0
for a in src:
    if str(a.get('alias', '')).startswith('Alerta Piscina'):
        nombre = '/config/automations/piscina_' + ('baja' if 'bajo' in a['alias'] else 'alta') + '.yaml'
        acciones = a.get('actions') or []
        if acciones and not any(x.get('action') == 'rest_command.whatsapp_alerta_2' for x in acciones):
            acciones.append({'action': 'rest_command.whatsapp_alerta_2', 'data': {'mensaje': acciones[0]['data']['mensaje']}})
        if not os.path.exists(nombre):
            yaml.safe_dump(a, open(nombre, 'w', encoding='utf-8'), allow_unicode=True, sort_keys=False)
            hechas += 1
            print('Creado', nombre, '-', a['alias'])
print('Alertas de piscina recuperadas:', hechas)
PY
sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config > /tmp/check_ha.txt 2>&1 || true
if grep -qiE "Failed config|Invalid config" /tmp/check_ha.txt; then
  echo "Configuracion con errores; se quitan los archivos nuevos:"; grep -iE -A5 "Failed config|Invalid config" /tmp/check_ha.txt | head -20
  sudo rm -f $C/automations/piscina_baja.yaml $C/automations/piscina_alta.yaml; exit 1
fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: Home Assistant reiniciado"
