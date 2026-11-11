#!/bin/bash
# Bot-Acura (11/11/2026): disponibilidad primero -> datos -> ubicación -> políticas -> pago -> menú. Sin cupo: fechas cercanas con cupo.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4); [ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA; echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M); BC=app/Http/Controllers/Api/BotController.php; BP=app/Services/BotPromptService.php
cp $BC $BC.bak.$TS; cp $BP $BP.bak.$TS
R() { cp $BC.bak.$TS $BC; cp $BP.bak.$TS $BP; echo "ERROR: $1. Restaurado, no cambió nada."; exit 1; }
curl -fsSL $U/bot/patch_pago_confirma_1111.py | python3 - || R "parche de pago"
curl -fsSL $U/bot/patch_flujo_1111.py | python3 - || R "parche de flujo"
php -l $BC >/dev/null || R "sintaxis BotController"; php -l $BP >/dev/null || R "sintaxis BotPromptService"
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
M() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" "$@" 2>/dev/null; }
M -e "SELECT * FROM bot_pasos_flujo\G" > ~/respaldo_guion_$TS.txt
curl -fsSL $U/bot/guion_flujo_1111.sql | M || R "guion"
php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null
echo "Guion:"; M -e "SELECT numero_paso, titulo FROM bot_pasos_flujo WHERE activo=1 ORDER BY orden"
echo; echo "== Prueba simulada (no envía nada):"
curl -fsSL $U/bot/probar_orden.php -o /tmp/po.php && sudo -u www-data php /tmp/po.php 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/po.php
echo "Si algo sale mal: cp $BC.bak.$TS $BC; cp $BP.bak.$TS $BP  (guion: ~/respaldo_guion_$TS.txt)"
echo LISTO
