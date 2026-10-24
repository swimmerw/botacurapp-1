#!/bin/bash
# Hasta el 31 de octubre: el bot cobra los valores de octubre, programas S jueves y viernes, sin mínimo de lunes a jueves.
# Desde noviembre siguen los del afiche. En marzo vuelve solo a temporada baja (meses 3 a 10).
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/oct
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M); F="app/Services/BotTarifasService.php app/Services/BotPromptService.php"
for f in $F; do cp "$f" "$f.bak.$TS"; done
curl -fsSL $R/patch.py -o /tmp/oct_patch.py && curl -fsSL $R/datos.php -o /tmp/oct_datos.php || exit 1
if ! python3 /tmp/oct_patch.py "$PWD" || ! php -l app/Services/BotTarifasService.php >/dev/null || ! php -l app/Services/BotPromptService.php >/dev/null; then
  for f in $F; do cp "$f.bak.$TS" "$f"; done; echo "ERROR: restaurado"; exit 1; fi
php /tmp/oct_datos.php; rm -f /tmp/oct_patch.py /tmp/oct_datos.php
php artisan config:clear >/dev/null; php artisan queue:restart >/dev/null; echo LISTO
