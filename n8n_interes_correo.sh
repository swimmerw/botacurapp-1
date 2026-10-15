#!/bin/bash
# Raspberry: flujo n8n "Botacura - Dias pedidos por correo" (15/10/2026).
# Cada hora busca borradores "NO ENVIAR - INTERES FECHAS" (los deja la tarea de correos), manda su JSON a
# BotacurApp (POST /api/cotizador/interes-fechas con la credencial "Cotizador BotacurApp") y borra el borrador.
set -e
D=/home/botacura/n8n-data
sudo docker ps --format '{{.Names}}' | grep -qx n8n || { echo "No esta corriendo el contenedor n8n"; exit 1; }
DEST=$(sudo docker inspect n8n --format '{{range .Mounts}}{{if eq .Source "'$D'"}}{{.Destination}}{{end}}{{end}}')
[ -n "$DEST" ] || { echo "No encuentro donde monta $D dentro de n8n"; exit 1; }
sudo docker exec n8n n8n export:workflow --id=botacuraDisponib1 --output=$DEST/_disp.json >/dev/null
BASE=$(sudo python3 -c "
import json,re,sys
t=open('$D/_disp.json').read()
m=re.search(r'(https?://[^\"\s]+?)/api/cotizador/', t)
print(m.group(1) if m else '')")
sudo rm -f $D/_disp.json
[ -n "$BASE" ] || { echo "No pude sacar la URL de BotacurApp del flujo de disponibilidad"; exit 1; }
echo "BotacurApp: $BASE"
sudo python3 - "$BASE" "$D/_interes.json" <<'PY'
import json, sys
base, out = sys.argv[1], sys.argv[2]
G = "https://gmail.googleapis.com/gmail/v1/users/me/drafts"
gmail = {"authentication": "predefinedCredentialType", "nodeCredentialType": "gmailOAuth2"}
gcred = {"gmailOAuth2": {"id": "gmailBotacura01", "name": "Gmail Botacura"}}
def http(name, pos, params, cred):
    return {"parameters": params, "name": name, "type": "n8n-nodes-base.httpRequest", "typeVersion": 4.2,
            "position": pos, "credentials": cred, "id": name.replace(" ", "_")}
nodes = [
  {"parameters": {"rule": {"interval": [{"field": "hours", "hoursInterval": 1, "triggerAtMinute": 25}]}},
   "name": "Cada hora", "type": "n8n-nodes-base.scheduleTrigger", "typeVersion": 1.2, "position": [0, 0], "id": "Cada_hora"},
  http("Buscar borradores", [220, 0], dict(gmail, url=G, sendQuery=True, queryParameters={"parameters": [
       {"name": "q", "value": 'subject:"NO ENVIAR - INTERES FECHAS"'}, {"name": "maxResults", "value": "20"}]}), gcred),
  {"parameters": {"jsCode": "const d = $input.first().json.drafts || [];\nreturn d.map(x => ({ json: { draftId: x.id } }));"},
   "name": "Uno por borrador", "type": "n8n-nodes-base.code", "typeVersion": 2, "position": [440, 0], "id": "Uno_por_borrador"},
  http("Leer borrador", [660, 0], dict(gmail, url="=" + G + "/{{ $json.draftId }}", sendQuery=True,
       queryParameters={"parameters": [{"name": "format", "value": "full"}]}), gcred),
  {"parameters": {"jsCode": r"""const out = [];
for (const it of $input.all()) {
  const draftId = it.json.id;
  const textos = [];
  const walk = p => {
    if (!p) return;
    if (p.body && p.body.data && (p.mimeType || '').startsWith('text/')) {
      let t = Buffer.from(p.body.data.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8');
      if ((p.mimeType || '') === 'text/html') t = t.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '').replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&nbsp;/g, ' ');
      textos.push(t);
    }
    (p.parts || []).forEach(walk);
  };
  walk(it.json.message && it.json.message.payload);
  let datos = null;
  for (const t of textos) {
    const a = t.indexOf('{'), b = t.lastIndexOf('}');
    if (a >= 0 && b > a) { try { datos = JSON.parse(t.slice(a, b + 1)); break; } catch (e) {} }
  }
  if (datos && Array.isArray(datos.consultas)) out.push({ json: { draftId, cuerpo: { consultas: datos.consultas } } });
}
return out;"""}, "name": "Sacar JSON", "type": "n8n-nodes-base.code", "typeVersion": 2, "position": [880, 0], "id": "Sacar_JSON"},
  http("Enviar a BotacurApp", [1100, 0], {"method": "POST", "url": base + "/api/cotizador/interes-fechas",
       "authentication": "genericCredentialType", "genericAuthType": "httpHeaderAuth",
       "sendBody": True, "specifyBody": "json", "jsonBody": "={{ JSON.stringify($json.cuerpo) }}"},
       {"httpHeaderAuth": {"id": "cotizadorBotacura01", "name": "Cotizador BotacurApp"}}),
  http("Borrar borrador", [1320, 0], dict(gmail, method="DELETE",
       url="=" + G + "/{{ $('Sacar JSON').item.json.draftId }}"), gcred),
]
names = [n["name"] for n in nodes]
conn = {names[i]: {"main": [[{"node": names[i + 1], "type": "main", "index": 0}]]} for i in range(len(names) - 1)}
wf = {"id": "botacuraInteres1", "name": "Botacura – Dias pedidos por correo a BotacurApp",
      "nodes": nodes, "connections": conn, "active": False, "settings": {"executionOrder": "v1"}}
json.dump([wf], open(out, "w"), ensure_ascii=False, indent=1)
print("Flujo armado")
PY
sudo docker exec n8n n8n import:workflow --input=$DEST/_interes.json
sudo rm -f $D/_interes.json
sudo docker exec n8n n8n publish:workflow --id=botacuraInteres1 2>/dev/null \
  || sudo docker exec n8n n8n update:workflow --id=botacuraInteres1 --active=true
sudo docker restart n8n >/dev/null
echo "LISTO: flujo 'Botacura – Dias pedidos por correo a BotacurApp' publicado (cada hora, minuto 25). n8n reiniciado."
