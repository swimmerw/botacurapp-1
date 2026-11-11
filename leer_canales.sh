#!/bin/bash
# Solo lectura (código, sin datos de clientes) + estado de imágenes, audios e Instagram. 11/11/2026
cd /var/www/html/botacurapp || exit 1
D=public/rev-929a3423dbcd; mkdir -p $D
for f in app/Http/Controllers/Api/WhatsAppWebhookController.php app/Services/WhisperService.php app/Http/Controllers/Api/InstagramWebhookController.php; do
  [ -f "$f" ] && cp "$f" $D/$(basename "$f" .php).txt
done
grep -n "instagram\|whatsapp\|facebook\|messenger" routes/api.php | cut -c1-200 > $D/rutas.txt
chmod 644 $D/*.txt
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/estado_canales.php -o /tmp/ec.php && sudo -u www-data php /tmp/ec.php 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/ec.php
echo LISTO
