#!/bin/bash
# Frase clave #reiniciar para el equipo (13/10/2026)
cd /var/www/html/botacurapp || exit 1
rm -rf public/rev-83a74f8055dd   # copia temporal de lectura
W=app/Http/Controllers/Api/WhatsAppWebhookController.php
C=app/Services/BotCasoService.php
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot_reiniciar.py?v=$(date +%s)" -o /tmp/bot_reiniciar.py || exit 1
python3 /tmp/bot_reiniciar.py || exit 1
for f in $W $C; do
  php -l $f || { for g in $W $C; do b=$(ls $g.bak.* 2>/dev/null | sort | tail -1); [ -n "$b" ] && cp "$b" $g; done; echo "ERROR de sintaxis: archivos restaurados"; exit 1; }
done
grep -q '^BOT_TELEFONOS_EQUIPO=' .env || echo 'BOT_TELEFONOS_EQUIPO=56961910398,56940014282' >> .env
php artisan config:clear >/dev/null
echo "LISTO: escribe #reiniciar al bot desde 56961910398 o 56940014282"
