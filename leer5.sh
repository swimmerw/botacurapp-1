#!/bin/bash
# Copia temporal (solo lectura) para revisar Utilidad e Ingresos percibidos. Se borra con: rm -rf /var/www/html/botacurapp/public/rev-4580a09d3e5c
cd /var/www/html/botacurapp
D=public/rev-4580a09d3e5c; mkdir -p $D
cp app/Http/Controllers/ReporteFinancieroController.php $D/ctrl.txt
cp resources/views/themes/backoffice/pages/reporte/financiero/utilidad.blade.php $D/util.txt
cp resources/views/themes/backoffice/pages/reporte/ingreso/percibido.blade.php $D/perc.txt
chmod 644 $D/*.txt; ls -la $D; echo LISTO
