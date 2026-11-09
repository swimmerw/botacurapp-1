#!/bin/bash
# Arreglos del bot (09/11/2026): comprobantes con otro telefono, cupos vencidos mientras el cliente paga,
# comprobantes sin reserva, mensajes atrasados/duplicados. Ademas libera los chats silenciados por la sincronizacion.
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/patch_pagos_0911.py -o /tmp/patch_pagos_0911.py || exit 1
OUT=$(sudo python3 /tmp/patch_pagos_0911.py); echo "$OUT"
if echo "$OUT" | grep -q PARCHE_OK; then
  ST=$(echo "$OUT" | grep PARCHE_OK | awk '{print $2}')
  for f in WhatsAppWebhookController BotReservaController BotController; do
    F=app/Http/Controllers/Api/$f.php
    if ! php -l $F >/dev/null; then
      for g in WhatsAppWebhookController BotReservaController BotController; do sudo cp app/Http/Controllers/Api/$g.php.bak.$ST app/Http/Controllers/Api/$g.php; done
      echo "ERROR de sintaxis en $f: se restauraron los 3 archivos"; exit 1
    fi
  done
  echo "Sintaxis OK (respaldos .bak.$ST)"
elif ! echo "$OUT" | grep -q YA_APLICADO; then
  echo "No se aplico nada"; exit 1
fi
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"\r'); set +a
export MYSQL_PWD="$DB_PASSWORD"; M="mysql -h${DB_HOST:-127.0.0.1} -u$DB_USERNAME $DB_DATABASE"
# Chats silenciados por la sincronizacion: el arreglo de las 11:35 los dejo en pausa hasta las 14:35 por la zona horaria
$M -e "UPDATE bot_conversaciones c JOIN bot_conv_silencio_bak_20261109 b ON b.id=c.id SET c.humano_hasta=NULL, c.requiere_humano=0 WHERE c.updated_at <= '2026-11-09 09:40:00'; SELECT ROW_COUNT() chats_liberados;"
# Reserva 2803 (Mickael): que aparezca en Verificacion de pagos
$M -e "UPDATE ventas SET estado_pago='pendiente_verificacion' WHERE id=2802 AND id_reserva=2803 AND estado_pago='pendiente'; SELECT id, estado_pago FROM ventas WHERE id=2802;"
php artisan config:clear >/dev/null 2>&1
echo "LISTO"
