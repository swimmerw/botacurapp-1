#!/bin/bash
cd /var/www/html/botacurapp || exit 1
TS=$(date +%Y%m%d%H%M); C=app/Http/Controllers/BotEjecutivoController.php; V=resources/views/themes/backoffice/pages/bot-ejecutivo/index.blade.php
sudo cp $C $C.bak.$TS; [ -f $V ] && sudo cp $V $V.bak.$TS
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/patch_panel_ejecutivo_1111.py -o /tmp/ppe.py && sudo python3 /tmp/ppe.py; rm -f /tmp/ppe.py
php -l $C >/dev/null || { sudo cp $C.bak.$TS $C; echo "ERROR de sintaxis: restaurado"; exit 1; }
sudo -u www-data php artisan view:clear >/dev/null
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
echo "Pedidos de EJECUTIVO pendientes:"
mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT id, telefono, nombre, created_at FROM bot_mensajes_pendientes WHERE tipo='ejecutivo' AND estado='pendiente'" 2>/dev/null || echo "(tabla con otro nombre; revisa el panel)"
grep -h "pedido de ejecutivo" storage/logs/laravel-$(date +%Y-%m-%d).log 2>/dev/null | tail -3 | cut -c1-300
echo LISTO
