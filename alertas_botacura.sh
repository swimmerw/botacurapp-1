#!/bin/bash
# Alertas de Botacura por WhatsApp solo al administrador (piscina, SOS, gas). Correr en la Raspberry.
set -e
A=/home/botacura/homeassistant/config/automations
NUEVOS="$A/piscina_baja.yaml $A/piscina_alta.yaml $A/sos_whatsapp.yaml $A/gas_bajo_whatsapp.yaml"

# 1. Piscina: recuperar desde archivo_viejo, solo al administrador
sudo docker exec -i homeassistant python3 - <<'PY'
import yaml, os
src = yaml.safe_load(open('/config/archivo_viejo/automations.yaml', encoding='utf-8')) or []
for a in src:
    if str(a.get('alias', '')).startswith('Alerta Piscina'):
        a['actions'] = [x for x in (a.get('actions') or []) if x.get('action') != 'rest_command.whatsapp_alerta_2']
        nombre = '/config/automations/piscina_' + ('baja' if 'bajo' in a['alias'] else 'alta') + '.yaml'
        yaml.safe_dump(a, open(nombre, 'w', encoding='utf-8'), allow_unicode=True, sort_keys=False)
        print('Piscina:', a['alias'])
PY

# 2. SOS de tinajas y masajes
sudo tee $A/sos_whatsapp.yaml >/dev/null <<'YML'
id: sos_whatsapp_administrador
alias: SOS | WhatsApp al administrador
description: Avisa por WhatsApp (solo al administrador) cuando se activa un boton SOS de Botacura
mode: queued
triggers:
  - trigger: state
    entity_id:
      - input_boolean.sos_tinaja_1_activo
      - input_boolean.sos_tinaja_2_activo
      - input_boolean.sos_masajes_activo
    from: 'off'
    to: 'on'
conditions: []
actions:
  - action: rest_command.whatsapp_alerta
    data:
      mensaje: >-
        SOS {{ {'input_boolean.sos_tinaja_1_activo': 'Tinaja 1', 'input_boolean.sos_tinaja_2_activo': 'Tinaja 2',
        'input_boolean.sos_masajes_activo': 'Masajes'}[trigger.entity_id] }} activado a las {{ now().strftime('%H:%M') }}
YML

# 3. Gas bajo en tinajas (menos de 20% restante)
sudo tee $A/gas_bajo_whatsapp.yaml >/dev/null <<'YML'
id: gas_bajo_tinajas_whatsapp
alias: Gas | Cilindro bajo en tinajas (WhatsApp)
description: Avisa por WhatsApp (solo al administrador) cuando al cilindro de una tinaja le queda menos de 20%
mode: queued
triggers:
  - trigger: numeric_state
    entity_id:
      - sensor.porcentaje_gas_restante_tinaja_1
      - sensor.porcentaje_gas_restante_tinaja_2
    below: 20
conditions: []
actions:
  - action: rest_command.whatsapp_alerta
    data:
      mensaje: >-
        gas bajo en {{ 'Tinaja 1' if 'tinaja_1' in trigger.entity_id else 'Tinaja 2' }}: queda {{ trigger.to_state.state }}%
        (aprox. {{ states('sensor.horas_restantes_gas_' ~ ('tinaja_1' if 'tinaja_1' in trigger.entity_id else 'tinaja_2')) }} h de uso)
YML

# 4. Validar y reiniciar
sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config > /tmp/check_ha.txt 2>&1 || true
if grep -qiE "Failed config|Invalid config" /tmp/check_ha.txt; then
  echo "Configuracion con errores; se quitan los archivos nuevos:"; grep -iE -A6 "Failed config|Invalid config" /tmp/check_ha.txt | head -25
  sudo rm -f $NUEVOS; exit 1
fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: alertas de piscina, SOS y gas creadas (solo al administrador). Home Assistant reiniciado."
