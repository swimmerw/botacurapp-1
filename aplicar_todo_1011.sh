#!/bin/bash
# Correcciones del bot 10/11/2026, todo junto. Seguro de correr mas de una vez.
cd /var/www/html/botacurapp || exit 1
G=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
W=app/Http/Controllers/Api/WhatsAppWebhookController.php
echo "== 1/5 Comprobantes, cupos vencidos, mensajes atrasados (09/11)"
if grep -q esComprobanteSinReserva $W; then echo "ya estaba"; else curl -fsSL $G/aplicar_bot_pagos.sh | bash || exit 1; fi
echo "== 2/5 Mensaje de ausencia y avisos de Meta"
curl -fsSL $G/bot/patch_ecos_1011.py -o /tmp/p1011.py && OUT=$(sudo python3 /tmp/p1011.py); echo "$OUT"; rm -f /tmp/p1011.py
if ! php -l $W >/dev/null; then sudo cp "$(ls $W.bak.* | sort | tail -1)" $W; echo "ERROR de sintaxis: webhook restaurado"; exit 1; fi
echo "== 3/5 Pantalla Comprobantes sin reserva"
curl -fsSL $G/aplicar_comprobantes_sin_reserva.sh | bash | tail -4
echo "== 4/5 Pantalla Reservas en vivo"
curl -fsSL $G/aplicar_reservas_en_vivo.sh | bash | tail -2
echo "== 5/5 Liberar chats pausados por el mensaje de ausencia"
curl -fsSL $G/bot/liberar_ausencia.php -o /tmp/lib.php && sudo -u www-data php /tmp/lib.php; rm -f /tmp/lib.php
php artisan config:clear >/dev/null 2>&1
echo "== Ultima actividad del bot"
grep -h "" storage/logs/laravel-$(date +%Y-%m-%d).log | grep -v "WA sync\|Verificación fallida" | grep "WhatsApp\|\[Bot\]" | tail -8 | cut -c1-220
echo "== Errores de la ultima hora: $(grep -h "$(date +%Y-%m-%d) $(date +%H):" storage/logs/laravel-$(date +%Y-%m-%d).log | grep -c "production.ERROR")"
grep -h "$(date +%Y-%m-%d) $(date +%H):" storage/logs/laravel-$(date +%Y-%m-%d).log | grep "production.ERROR" | tail -3 | cut -c1-300
echo "LISTO"
