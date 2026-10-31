#!/bin/bash
# Menús: postres del día. Botón "Ingresar postres" abre un modal (nombre y cantidad) para cocina, administración y jefe de local;
# el bloque muestra los postres con + y − para ajustar. Actualiza la versión anterior. Se puede correr de nuevo.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/postres
echo "Version: ${SHA:0:7}"
T=$(date +%Y%m%d%H%M)
V=resources/views/themes/backoffice/pages/cocina/index.blade.php
FILES="routes/web.php app/Http/Controllers/MenuController.php $V"
for f in $FILES; do sudo cp $f $f.bak.$T; done
restaurar(){ for f in $FILES; do sudo cp $f.bak.$T $f; done; echo "ERROR: $1 (se restauraron los respaldos)"; exit 1; }
MIG=database/migrations/2026_10_31_000001_create_postres_dia_table.php
curl -fsSL $R/PostreDiaController.php -o /tmp/pdc.php || exit 1
curl -fsSL $R/2026_10_31_000001_create_postres_dia_table.php -o /tmp/pdm.php || exit 1
curl -fsSL $R/patch.py -o /tmp/pdp.py || exit 1
sudo cp /tmp/pdc.php app/Http/Controllers/PostreDiaController.php
sudo cp /tmp/pdm.php $MIG
sudo chown www-data:www-data app/Http/Controllers/PostreDiaController.php $MIG
sudo python3 /tmp/pdp.py "$PWD" || restaurar "parche"
for f in routes/web.php app/Http/Controllers/MenuController.php app/Http/Controllers/PostreDiaController.php; do php -l $f >/dev/null || restaurar "sintaxis en $f"; done
sudo -u www-data php artisan migrate --path=$MIG --force 2>&1 | grep -v Deprecated
sudo -u www-data php artisan route:clear >/dev/null; sudo -u www-data php artisan view:clear >/dev/null
sudo -u www-data php artisan route:list 2>/dev/null | grep -q cocina.postres.store && echo "Ruta OK" || echo "OJO: no veo la ruta"
rm -f /tmp/pdc.php /tmp/pdm.php /tmp/pdp.py
echo LISTO
