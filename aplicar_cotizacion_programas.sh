#!/bin/bash
# Cotizador (crear): muestra solo los programas activos y agrega el botón "Ir a programas".
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
F="app/Http/Controllers/CotizacionController.php resources/views/themes/backoffice/pages/cotizacion/create.blade.php"
for f in $F; do cp "$f" "$f.bak.$TS"; done
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/cotprog/patch.py -o /tmp/cotprog.py || { echo "ERROR bajando"; exit 1; }
if ! python3 /tmp/cotprog.py "$PWD" || ! php -l app/Http/Controllers/CotizacionController.php >/dev/null; then
  for f in $F; do cp "$f.bak.$TS" "$f"; done; echo "ERROR: restaurado, nada cambió"; exit 1
fi
rm -f /tmp/cotprog.py; php artisan view:clear >/dev/null
php artisan tinker --execute="echo 'Programas activos: '.App\Programa::activos()->count().' de '.App\Programa::count().PHP_EOL;" 2>/dev/null
echo LISTO
