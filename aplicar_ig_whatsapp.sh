#!/bin/bash
# Instagram: las reservas se derivan a WhatsApp desde el comienzo (11/11/2026).
cd /var/www/html/botacurapp || exit 1
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
TS=$(date +%Y%m%d%H%M); BC=app/Http/Controllers/Api/BotController.php; cp $BC $BC.bak.$TS
curl -fsSL $U/bot/patch_ig_whatsapp_1111.py | python3 - || { cp $BC.bak.$TS $BC; echo "ERROR: restaurado"; exit 1; }
php -l $BC >/dev/null || { cp $BC.bak.$TS $BC; echo "ERROR de sintaxis: restaurado"; exit 1; }
curl -fsSL $U/bot/texto_ig_1111.php -o /tmp/ti.php && php /tmp/ti.php 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/ti.php
php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null
echo "Si algo sale mal: cp $BC.bak.$TS $BC"; echo LISTO
