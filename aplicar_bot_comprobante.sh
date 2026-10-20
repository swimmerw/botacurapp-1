#!/bin/bash
# Bot: revisar comprobante antes de crear la reserva (13/10/2026)
cd /var/www/html/botacurapp || exit 1
rm -rf public/rev-b9e5a57df230   # copia temporal de lectura (tenia datos de la clienta)
F=app/Http/Controllers/Api/WhatsAppWebhookController.php
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot_comprobante.py -o /tmp/bot_comprobante.py || exit 1
python3 /tmp/bot_comprobante.py || exit 1
if ! php -l $F; then
  cp "$(ls $F.bak.* | sort | tail -1)" $F; echo "ERROR de sintaxis: archivo restaurado"; exit 1
fi
php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null 2>&1 || true
echo "LISTO: el bot revisa el comprobante antes de crear la reserva"
