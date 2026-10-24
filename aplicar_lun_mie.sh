#!/bin/bash
# Lunes a miércoles: el día se abre con un grupo de 10+; con el grupo ya reservado se aceptan parejas y grupos chicos.
# Aplica al bot, al calendario y al pago de la web, al PDF de cotización. Las compras web quedan agendadas (ubicación y visita).
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/lunmie
echo "Version: ${SHA:0:7}"
grep -q "esTemporadaBaja" app/Services/BotTarifasService.php || { echo "Primero corre aplicar_noviembre.sh y aplicar_octubre.sh"; exit 1; }
TS=$(date +%Y%m%d%H%M)
F="app/Services/BotTarifasService.php app/Services/BotPromptService.php app/Http/Controllers/Api/VerificarDisponibilidadController.php app/Http/Controllers/Api/FechasDisponiblesController.php app/Jobs/ProcesarOrdenWoocommerce.php resources/views/pdf/cotizacion/viewPDF.blade.php"
for f in $F; do cp "$f" "$f.bak.$TS"; done
curl -fsSL $R/patch.py -o /tmp/lm_patch.py && curl -fsSL $R/datos.php -o /tmp/lm_datos.php || exit 1
ok=1; python3 /tmp/lm_patch.py "$PWD" || ok=0
for f in app/Services/BotTarifasService.php app/Services/BotPromptService.php app/Http/Controllers/Api/VerificarDisponibilidadController.php app/Http/Controllers/Api/FechasDisponiblesController.php app/Jobs/ProcesarOrdenWoocommerce.php; do php -l $f >/dev/null || ok=0; done
if [ $ok = 0 ]; then for f in $F; do cp "$f.bak.$TS" "$f"; done; echo "ERROR: restaurado, nada cambió"; exit 1; fi
php /tmp/lm_datos.php; rm -f /tmp/lm_patch.py /tmp/lm_datos.php
php artisan config:clear >/dev/null; php artisan view:clear >/dev/null; php artisan queue:restart >/dev/null
echo LISTO
