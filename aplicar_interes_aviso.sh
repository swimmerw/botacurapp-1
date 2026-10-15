#!/bin/bash
# Dias pedidos sin habilitar: aviso al pasar el umbral (correo + WhatsApp via Home Assistant). 15/10/2026
cd /var/www/html/botacurapp || exit 1
F=app/Services/BotInteresFechasService.php
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/f11e9b54903fccf6d6552117c4e7c3dcea868310/interes_aviso.py" -o /tmp/interes_aviso.py || exit 1
python3 /tmp/interes_aviso.py || exit 1
php -l $F >/dev/null || { cp "$(ls $F.bak.* | sort | tail -1)" $F; echo "ERROR de sintaxis: restaurado"; exit 1; }
echo "Sintaxis OK"
# Direccion interna para que Home Assistant mande el WhatsApp (por el tunel a la Raspberry)
PUERTO=$(sudo grep -ohE "localhost:[0-9]+" /etc/apache2/sites-available/ha-le-ssl.conf 2>/dev/null | head -1 | cut -d: -f2)
PUERTO=${PUERTO:-8823}
if ! grep -q '^HA_ALERTA_WEBHOOK=' .env; then
  ID="botacurapp_$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  cp .env .env.bak.$(date +%Y%m%d%H%M%S)
  echo "HA_ALERTA_WEBHOOK=http://127.0.0.1:$PUERTO/api/webhook/$ID" >> .env
else
  ID=$(grep '^HA_ALERTA_WEBHOOK=' .env | sed 's#.*/##')
fi
php artisan config:clear >/dev/null
echo
echo "LISTO en AWS. Ahora en la Raspberry corre esta linea (crea la alerta en Home Assistant):"
echo "curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/f11e9b54903fccf6d6552117c4e7c3dcea868310/alerta_app_ha.sh | bash -s $ID"
echo
echo "Despues, para probar desde AWS (te llega un WhatsApp de prueba):"
echo "curl -s -X POST -H 'Content-Type: application/json' -d '{\"mensaje\":\"Prueba de aviso desde BotacurApp\"}' \$(grep ^HA_ALERTA_WEBHOOK= /var/www/html/botacurapp/.env | cut -d= -f2) -w ' HTTP %{http_code}\n'"
