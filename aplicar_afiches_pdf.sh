#!/bin/bash
# 1) Configuración del bot › pestaña "Afiches": subir hasta 4 afiches que el bot envía al preguntar por programas.
# 2) PDF de cotización: términos en la 2ª página con reglas de grupos (+15 personas: cambios con 10 días), lunes a domingo y 10% lun–jue.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
V=resources/views/themes/backoffice/pages/bot-configuracion
NUEVOS="app/Services/BotAfichesService.php app/Http/Controllers/BotAfichesController.php $V/includes/_afiches.blade.php"
EDIT="app/Services/BotPromptService.php app/Http/Controllers/Api/BotController.php $V/index.blade.php routes/web.php resources/views/pdf/cotizacion/viewPDF.blade.php"
for f in $EDIT; do cp "$f" "$f.bak.$TS"; done
for f in $NUEVOS; do mkdir -p "$(dirname $f)"; curl -fsSL "$R/afiches/$f" -o "$f" || { echo "ERROR bajando $f"; exit 1; }; done
curl -fsSL $R/afiches/patch.py -o /tmp/af_patch.py && curl -fsSL $R/nov/patch_pdf.py -o /tmp/pdf_patch.py || exit 1
ok=1
python3 /tmp/af_patch.py "$PWD" && python3 /tmp/pdf_patch.py "$PWD" || ok=0
for f in app/Services/BotAfichesService.php app/Http/Controllers/BotAfichesController.php app/Services/BotPromptService.php app/Http/Controllers/Api/BotController.php routes/web.php; do php -l $f >/dev/null || ok=0; done
if [ $ok = 0 ]; then for f in $EDIT; do cp "$f.bak.$TS" "$f"; done; rm -f $NUEVOS; echo "ERROR: restaurado, nada cambió"; exit 1; fi
rm -f /tmp/af_patch.py /tmp/pdf_patch.py
mkdir -p public/uploads/bot/afiches && chown -R www-data:www-data public/uploads/bot/afiches 2>/dev/null; sudo chown -R www-data:www-data public/uploads/bot/afiches 2>/dev/null
php artisan tinker --execute="App\Services\BotAfichesService::asegurar(); echo 'Filas de afiches: '.DB::table('bot_secciones_texto')->where('categoria','afiches')->count().PHP_EOL;"
php artisan route:clear >/dev/null; php artisan view:clear >/dev/null; php artisan config:clear >/dev/null; php artisan queue:restart >/dev/null
echo "LISTO: Configuración del bot › Afiches"
