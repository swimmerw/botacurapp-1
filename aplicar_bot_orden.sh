#!/bin/bash
# Bot-Acura: orden de la reserva (10/11/2026)
# fecha + personas + programa -> turnos de tinaja -> ubicación -> confirmar -> recién ahí datos del cliente.
# También: fechas relativas ("este sábado", "sábado 21") resueltas en código y ubicación sin adornos.
cd /var/www/html/botacurapp || exit 1
rm -rf public/rev-2ef74d3f5b35   # copia temporal de lectura
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
echo "Version: ${SHA:0:7}"
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA
TS=$(date +%Y%m%d%H%M)
BC=app/Http/Controllers/Api/BotController.php
BP=app/Services/BotPromptService.php
cp $BC $BC.bak.$TS; cp $BP $BP.bak.$TS
restaurar() { cp $BC.bak.$TS $BC; cp $BP.bak.$TS $BP; echo "ERROR: $1. Restaurado, no cambió nada."; exit 1; }
curl -fsSL "$U/bot/patch_orden_1011.py" -o /tmp/patch_orden_1011.py || restaurar "no se pudo descargar el parche"
python3 /tmp/patch_orden_1011.py || restaurar "el parche no calzó"
php -l $BC >/dev/null || restaurar "sintaxis en BotController"
php -l $BP >/dev/null || restaurar "sintaxis en BotPromptService"
echo "Código OK"
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
M() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" "$@" 2>/dev/null; }
M -e "SELECT * FROM bot_pasos_flujo\G" > ~/respaldo_guion_$TS.txt
curl -fsSL "$U/bot/guion_orden_1011.sql" | M || restaurar "no se pudo actualizar el guion"
php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null
echo "Guion nuevo:"
M -e "SELECT numero_paso, titulo FROM bot_pasos_flujo WHERE activo=1 ORDER BY orden"
echo "Prueba de fechas (hoy $(date +%d/%m)):"
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $c=new App\Http\Controllers\Api\BotController(); $m=new ReflectionMethod($c,"notaFechasDelMensaje"); $m->setAccessible(true); foreach(["para este sábado no hay nada?","tienen para el sábado 21?","y el domingo 22 de noviembre"] as $t) echo "  ",$t," ->",$m->invoke($c,$t),"\n";'
echo "Respaldo del guion: ~/respaldo_guion_$TS.txt"
echo "Si algo sale mal: cp $BC.bak.$TS $BC; cp $BP.bak.$TS $BP"
echo LISTO
