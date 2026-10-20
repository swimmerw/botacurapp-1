#!/bin/bash
# Gift Cards por separado en Utilidad e Ingresos percibidos (13/10/2026)
cd /var/www/html/botacurapp || exit 1
rm -rf public/rev-4580a09d3e5c   # copia temporal de lectura
C=app/Http/Controllers/ReporteFinancieroController.php
VU=resources/views/themes/backoffice/pages/reporte/financiero/utilidad.blade.php
VP=resources/views/themes/backoffice/pages/reporte/ingreso/percibido.blade.php
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/finanzas5.py -o /tmp/finanzas5.py || exit 1
python3 /tmp/finanzas5.py || exit 1
if ! php -l $C; then
  for f in $C $VU $VP; do cp "$(ls $f.bak.* | sort | tail -1)" $f; done
  echo "ERROR de sintaxis: se restauraron los 3 archivos"; exit 1
fi
php artisan view:clear
echo "LISTO"
