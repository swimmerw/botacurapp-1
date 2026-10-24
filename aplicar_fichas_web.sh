#!/bin/bash
# Fichas de botacura.cl (afiche de noviembre, Gift Card $110.000) y la apertura con 10 personas de lunes a miércoles como regla interna: fuera de web, PDF y respuestas del bot.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}
echo "Version: ${SHA:0:7}"
for F in app/Services/ProgramaContentBuilder.php app/Services/BotTarifasService.php app/Services/BotPromptService.php app/Http/Controllers/Api/VerificarDisponibilidadController.php app/Http/Controllers/Api/FechasDisponiblesController.php resources/views/pdf/cotizacion/viewPDF.blade.php; do sudo cp $F $F.bak.$(date +%Y%m%d%H%M); done
curl -fsSL $R/fichas_web/patch.py -o /tmp/fw.py && curl -fsSL $R/fichas_web/datos.php -o /tmp/fw.php || exit 1
sudo python3 /tmp/fw.py "$PWD"; for F in app/Services/ProgramaContentBuilder.php app/Services/BotTarifasService.php app/Services/BotPromptService.php app/Http/Controllers/Api/VerificarDisponibilidadController.php app/Http/Controllers/Api/FechasDisponiblesController.php; do php -l $F >/dev/null || { echo "ERROR en $F"; exit 1; }; done; sudo -u www-data php artisan view:clear >/dev/null 2>&1
sudo -u www-data php /tmp/fw.php 2>&1 | grep -v '^PHP Deprecated' ; rm -f /tmp/fw.py /tmp/fw.php
echo LISTO
