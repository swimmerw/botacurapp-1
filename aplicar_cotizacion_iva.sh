#!/bin/bash
# Cotizaciones: los valores se ingresan NETOS y el total suma el IVA. Casilla opcional "ya incluyen IVA".
# Uso: ... | bash                -> instala y LISTA las cotizaciones afectadas (no las cambia)
#      ... | bash -s -- todas     -> además corrige todas      |  bash -s -- 116,117  -> solo esas
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/cotiva
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
F="app/Http/Controllers/CotizacionController.php resources/views/themes/backoffice/pages/cotizacion/create.blade.php resources/views/themes/backoffice/pages/cotizacion/edit.blade.php resources/views/themes/backoffice/pages/cotizacion/show.blade.php"
for f in $F; do cp "$f" "$f.bak.$TS"; done
curl -fsSL $U/patch.py -o /tmp/cotiva_patch.py && curl -fsSL $U/corregir.php -o cotiva_corregir.php || { echo "ERROR bajando"; exit 1; }
if ! python3 /tmp/cotiva_patch.py "$PWD" || ! php -l app/Http/Controllers/CotizacionController.php >/dev/null; then
  for f in $F; do cp "$f.bak.$TS" "$f"; done; echo "ERROR: restaurado, nada cambió"; exit 1
fi
php artisan view:clear >/dev/null
php cotiva_corregir.php "$1"; rm -f cotiva_corregir.php /tmp/cotiva_patch.py
echo LISTO
