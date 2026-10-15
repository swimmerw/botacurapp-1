#!/bin/bash
# Home Assistant: etiqueta de origen al inicio de cada alerta por WhatsApp (15/10/2026).
# Revisa automations/*.yaml; en cada accion rest_command.whatsapp_alerta(_2) antepone la etiqueta segun el nombre.
# Respaldo completo de automations/ antes de cambiar; si la configuracion queda con errores, se restaura.
set -e
C=/home/botacura/homeassistant/config
R=$C/archivo_viejo/respaldo_etiquetas_$(date +%Y%m%d%H%M%S).tar.gz
sudo mkdir -p $C/archivo_viejo
sudo tar czf $R -C $C automations
echo "Respaldo: $R"
sudo docker exec -i homeassistant python3 - <<'PY'
import glob, yaml
REGLAS = [  # (palabras en el nombre/id, etiqueta) - el primero que calce
    (('sos',), '🆘 SOS'),
    (('gas',), '🔥 GAS'),
    (('piscina',), '🏊 PISCINA'),
    (('jardin', 'jardín', 'ji ', 'ji_', 'cultivo', 'c1', 'c3'), '🌿 JARDÍN'),
    (('agua', 'riego', 'estanque'), '💧 AGUA'),
    (('botacurapp',), '🤖 APP'),
    (('tinaja', 'sauna', 'calefont'), '♨️ TINAJAS'),
]
ETIQUETAS = [e for _, e in REGLAS] + ['⚙️ SISTEMA']
def etiqueta(auto):
    t = (str(auto.get('alias', '')) + ' ' + str(auto.get('id', ''))).lower()
    for palabras, e in REGLAS:
        if any(p in t for p in palabras):
            return e
    return '⚙️ SISTEMA'
def recorrer(nodo, e, cambios):
    if isinstance(nodo, list):
        for x in nodo:
            recorrer(x, e, cambios)
    elif isinstance(nodo, dict):
        acc = nodo.get('action') or nodo.get('service')
        if acc in ('rest_command.whatsapp_alerta', 'rest_command.whatsapp_alerta_2') and isinstance(nodo.get('data'), dict) and 'mensaje' in nodo['data']:
            m = str(nodo['data']['mensaje'])
            if not any(m.lstrip().startswith(x) for x in ETIQUETAS):
                nodo['data']['mensaje'] = e + ' | ' + m.lstrip()
                cambios.append(acc)
        for k, v in nodo.items():
            if k != 'data':
                recorrer(v, e, cambios)
total = 0
for f in sorted(glob.glob('/config/automations/*.yaml')):
    try:
        d = yaml.safe_load(open(f, encoding='utf-8'))
    except Exception as ex:
        print('  (no se pudo leer %s: %s)' % (f, ex)); continue
    autos = d if isinstance(d, list) else [d]
    hubo = False
    for a in autos:
        if not isinstance(a, dict):
            continue
        cambios = []
        e = etiqueta(a)
        recorrer(a.get('actions', a.get('action', [])), e, cambios)
        if cambios:
            hubo = True; total += len(cambios)
            print('  %-12s %s (%d)' % (e, a.get('alias', a.get('id')), len(cambios)))
    if hubo:
        yaml.safe_dump(d, open(f, 'w', encoding='utf-8'), allow_unicode=True, sort_keys=False, width=1000)
print('Mensajes etiquetados: %d' % total)
PY
sudo docker exec homeassistant python3 -m homeassistant --script check_config -c /config > /tmp/check_ha.txt 2>&1 || true
if grep -qiE "Failed config|Invalid config" /tmp/check_ha.txt; then
  echo "Configuracion con errores; se restaura el respaldo:"; grep -iE -A6 "Failed config|Invalid config" /tmp/check_ha.txt | head -20
  sudo rm -rf $C/automations && sudo tar xzf $R -C $C; exit 1
fi
sudo docker restart homeassistant >/dev/null
echo "LISTO: alertas con etiqueta de origen. Home Assistant reiniciado."
