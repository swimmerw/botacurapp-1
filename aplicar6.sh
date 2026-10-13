#!/bin/bash
# Utilidad: Gift Card como ingreso el mes de la venta (13/10/2026)
cd /var/www/html/botacurapp || exit 1
C=app/Http/Controllers/ReporteFinancieroController.php
VU=resources/views/themes/backoffice/pages/reporte/financiero/utilidad.blade.php
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/finanzas6.py -o /tmp/finanzas6.py || exit 1
python3 /tmp/finanzas6.py || exit 1
if ! php -l $C; then
  for f in $C $VU; do cp "$(ls $f.bak.* | sort | tail -1)" $f; done
  echo "ERROR de sintaxis: se restauraron los archivos"; exit 1
fi
php artisan view:clear
echo "LISTO"
