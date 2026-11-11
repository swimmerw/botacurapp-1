#!/bin/bash
# Bot-Acura (11/11/2026): no confirma reservas si el pago no figura; no inventa N° de reserva ni cambios de servicios.
cd /var/www/html/botacurapp || exit 1
rm -f public/rev-c5e1f08b72d4.txt   # copia temporal del diagnóstico
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4); [ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA; echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M); BC=app/Http/Controllers/Api/BotController.php; BP=app/Services/BotPromptService.php
cp $BC $BC.bak.$TS; cp $BP $BP.bak.$TS
R() { cp $BC.bak.$TS $BC; cp $BP.bak.$TS $BP; echo "ERROR: $1. Restaurado, no cambió nada."; exit 1; }
curl -fsSL $U/bot/patch_pago_confirma_1111.py | python3 - || R "el parche no calzó"
php -l $BC >/dev/null || R "sintaxis BotController"; php -l $BP >/dev/null || R "sintaxis BotPromptService"
php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null
echo "Estados de pago de las ventas con visita futura (para revisar qué cuenta como pagado):"
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT COALESCE(v.estado_pago,'(vacío)') estado, COUNT(*) n, SUM(v.abono_programa>0) con_abono FROM ventas v JOIN reservas r ON r.id=v.id_reserva WHERE r.fecha_visita>=CURDATE() GROUP BY v.estado_pago" 2>/dev/null
echo "Si algo sale mal: cp $BC.bak.$TS $BC; cp $BP.bak.$TS $BP"
echo LISTO
