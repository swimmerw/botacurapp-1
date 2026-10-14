#!/bin/bash
# Jardin interior: alertas de humedad (40-55%), CO2 (400-1500 ppm) y temperatura (20-28 C).
# Avisa a ambos numeros al salir del rango (10 min seguidos) y recuerda cada 60 min si sigue fuera, maximo 25 avisos al dia (counter.jardin_alertas_hoy).
# Reemplaza las 3 alertas antiguas del jardin (se mueven a archivo_viejo, no se borran). Correr en la Raspberry.
set -e
C=/home/botacura/homeassistant/config
A=$C/automations
NUEVO=$A/jardin_alertas_ambiente.yaml
LUCES=$A/jardin_luces_aviso.yaml
VIEJOS=$(sudo grep -lE "1785382300003|1785382300004|1785382300005" $A/*.yaml 2>/dev/null || true)
for f in $VIEJOS; do
  if [ "$(sudo grep -c '^ *alias:' $f)" != "1" ]; then echo "ERROR: $f tiene mas de una automatizacion. No se cambio nada."; exit 1; fi
done
sudo mkdir -p $C/archivo_viejo/jardin_$(date +%Y%m%d)
DEST=$C/archivo_viejo/jardin_$(date +%Y%m%d)

sudo tee $NUEVO >/dev/null <<'YML'
id: jardin_interior_alertas_ambiente
alias: Jardin interior | Alertas de humedad, CO2 y temperatura
description: Rangos - humedad 40-55%, CO2 400-1500 ppm, temperatura 20-28 C. Aviso al salir del rango y recordatorio cada 60 min, maximo 25 avisos al dia.
mode: queued
max: 5
triggers:
  - trigger: numeric_state
    entity_id: sensor.sonoff_1002986d6a_temperature
    above: 28
    for: {minutes: 10}
  - trigger: numeric_state
    entity_id: sensor.sonoff_1002986d6a_temperature
    below: 20
    for: {minutes: 10}
  - trigger: numeric_state
    entity_id: sensor.sonoff_1002986d6a_humidity
    above: 55
    for: {minutes: 10}
  - trigger: numeric_state
    entity_id: sensor.sonoff_1002986d6a_humidity
    below: 40
    for: {minutes: 10}
  - trigger: numeric_state
    entity_id: sensor.sonoff_1002986d6a_co2
    above: 1500
    for: {minutes: 10}
  - trigger: numeric_state
    entity_id: sensor.sonoff_1002986d6a_co2
    below: 400
    for: {minutes: 10}
  - trigger: time_pattern
    minutes: 0
    id: recordatorio
  - trigger: time
    at: '00:00:00'
    id: reset_diario
  - trigger: homeassistant
    event: start
    id: inicio
conditions: []
actions:
  - if:
      - condition: trigger
        id: reset_diario
    then:
      - action: counter.reset
        target: {entity_id: counter.jardin_alertas_hoy}
      - stop: Contador diario reiniciado
  - variables:
      fuera: >-
        {% set ns = namespace(l=[]) %}
        {% set t = states('sensor.sonoff_1002986d6a_temperature') %}
        {% set h = states('sensor.sonoff_1002986d6a_humidity') %}
        {% set c = states('sensor.sonoff_1002986d6a_co2') %}
        {% if t | is_number %}{% set t = t | float %}
          {% if t > 28 %}{% set ns.l = ns.l + ['temperatura alta ' ~ t ~ ' C (rango 20-28)'] %}
          {% elif t < 20 %}{% set ns.l = ns.l + ['temperatura baja ' ~ t ~ ' C (rango 20-28)'] %}{% endif %}
        {% endif %}
        {% if h | is_number %}{% set h = h | float %}
          {% if h > 55 %}{% set ns.l = ns.l + ['humedad alta ' ~ h ~ '% (rango 40-55)'] %}
          {% elif h < 40 %}{% set ns.l = ns.l + ['humedad baja ' ~ h ~ '% (rango 40-55)'] %}{% endif %}
        {% endif %}
        {% if c | is_number %}{% set c = c | float | round(0) | int %}
          {% if c > 1500 %}{% set ns.l = ns.l + ['CO2 alto ' ~ c ~ ' ppm (rango 400-1500, ventilar)'] %}
          {% elif c < 400 %}{% set ns.l = ns.l + ['CO2 bajo ' ~ c ~ ' ppm (rango 400-1500)'] %}{% endif %}
        {% endif %}
        {{ ns.l | join(', ') }}
  - condition: template
    value_template: "{{ fuera | length > 0 }}"
  - condition: template
    value_template: "{{ states('counter.jardin_alertas_hoy') | int(0) < 25 }}"
  - variables:
      texto: >-
        Jardin interior: {{ fuera }}{{ ' (sigue fuera de rango)' if trigger.id == 'recordatorio' else '' }}{{
        ' - ultimo aviso de hoy (limite de 25), se reanuda a medianoche' if states('counter.jardin_alertas_hoy') | int(0) == 24 else '' }}
  - action: rest_command.whatsapp_alerta
    data:
      mensaje: "{{ texto }}"
  - action: rest_command.whatsapp_alerta_2
    data:
      mensaje: "{{ texto }}"
  - action: counter.increment
    target: {entity_id: counter.jardin_alertas_hoy}
YML

sudo tee $LUCES >/dev/null <<'YML'
id: jardin_interior_aviso_luces
alias: Jardin interior | Aviso de luces C1-C4 encendidas o apagadas
description: Avisa a ambos numeros cuando las luces C1 y C2 y C3 y C4 se encienden, se apagan o quedan sin conexion 10 min.
mode: queued
max: 10
triggers:
  - trigger: state
    entity_id:
      - switch.sonoff_10027e5aa0_1
      - switch.sonoff_10026dca47_1
    from: 'off'
    to: 'on'
    id: encendio
  - trigger: state
    entity_id:
      - switch.sonoff_10027e5aa0_1
      - switch.sonoff_10026dca47_1
    from: 'on'
    to: 'off'
    id: apago
  - trigger: state
    entity_id:
      - switch.sonoff_10027e5aa0_1
      - switch.sonoff_10026dca47_1
    from: ['on', 'off']
    to: unavailable
    for: {minutes: 10}
    id: sin_conexion
conditions: []
actions:
  - variables:
      nombre: >-
        {{ {'switch.sonoff_10027e5aa0_1': 'Luces C1 y C2', 'switch.sonoff_10026dca47_1': 'Luces C3 y C4'}[trigger.entity_id] }}
      texto: >-
        {% if trigger.id == 'encendio' %}{{ nombre }} se encendieron a las {{ now().strftime('%H:%M') }}
        {% elif trigger.id == 'apago' %}{{ nombre }} se apagaron a las {{ now().strftime('%H:%M') }}
        {% else %}{{ nombre }} sin conexion desde hace 10 min (no se sabe si estan encendidas){% endif %}
  - action: rest_command.whatsapp_alerta
    data:
      mensaje: "Jardin interior: {{ texto }}"
  - action: rest_command.whatsapp_alerta_2
    data:
      mensaje: "Jardin interior: {{ texto }}"
YML

for f in $VIEJOS; do sudo mv "$f" $DEST/; echo "Movido a archivo_viejo: $(basename $f)"; done

sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config > /tmp/check_ha.txt 2>&1 || true
if grep -qiE "Failed config|Invalid config" /tmp/check_ha.txt; then
  echo "Configuracion con errores; se deja todo como estaba:"; grep -iE -A6 "Failed config|Invalid config" /tmp/check_ha.txt | head -25
  sudo rm -f $NUEVO $LUCES; for f in $VIEJOS; do sudo mv $DEST/$(basename $f) $A/; done
  exit 1
fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: alertas del jardin interior creadas (humedad 40-55%, CO2 400-1500 ppm, T 20-28 C, luces C1-C4). Home Assistant reiniciado."
