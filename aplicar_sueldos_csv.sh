#!/bin/bash
# Sueldos y CSV del banco:
#  - El CSV no vuelve a incluir semanas ya pagadas.
#  - Cada CSV descargado guarda qué semana y monto va para cada persona; cuando llega el aviso
#    del banco, esa semana queda "Pagado" sola (también con bono, o con un solo aviso por todo el archivo).
#  - Lee los avisos de hoy, vuelve a cruzar y muestra el diagnóstico de una persona.
# Uso: curl -fsSL .../aplicar_sueldos_csv.sh | bash -s Elohim
cd /var/www/html/botacurapp || exit 1
QUIEN="${1:-Elohim}"
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/sueldos_csv
echo "Version: ${SHA:0:7}"
T=$(date +%Y%m%d%H%M)
FILES="app/Services/CsvPagoBancoService.php app/Http/Controllers/SueldoController.php app/Finanzas/Correo/ConciliadorSalidas.php"
for f in $FILES; do sudo cp $f $f.bak.$T; done
restaurar(){ for f in $FILES; do sudo cp $f.bak.$T $f; done; sudo rm -f app/Finanzas/SueldoCsv.php; echo "ERROR: $1 (se restauraron los respaldos)"; exit 1; }
MIG=database/migrations/2026_10_30_000001_create_sueldo_csv_lineas_table.php
curl -fsSL $R/SueldoCsv.php -o /tmp/scsv.php || exit 1
curl -fsSL $R/2026_10_30_000001_create_sueldo_csv_lineas_table.php -o /tmp/scsvm.php || exit 1
curl -fsSL $R/patch.py -o /tmp/scsvp.py || exit 1
curl -fsSL $R/diag_pago.php -o diag_pago_tmp_$T.php || exit 1
sudo cp /tmp/scsv.php app/Finanzas/SueldoCsv.php
sudo cp /tmp/scsvm.php $MIG
sudo chown www-data:www-data app/Finanzas/SueldoCsv.php $MIG
sudo python3 /tmp/scsvp.py "$PWD" || restaurar "parche"
for f in $FILES app/Finanzas/SueldoCsv.php; do php -l $f >/dev/null || restaurar "sintaxis en $f"; done
sudo -u www-data php artisan migrate --path=$MIG --force 2>&1 | grep -v Deprecated
sudo -u www-data php artisan config:clear >/dev/null; sudo -u www-data php artisan view:clear >/dev/null
echo "---- Leyendo avisos del banco de hoy y ayer, y volviendo a cruzar"
php artisan finanzas:leer-correos --dias=2 2>&1 | grep -v Deprecated | tail -n 8
php artisan finanzas:leer-correos --recruzar 2>&1 | grep -v Deprecated | tail -n 4
echo "---- Diagnóstico: $QUIEN"
php diag_pago_tmp_$T.php "$QUIEN" 2>&1 | grep -v Deprecated
rm -f diag_pago_tmp_$T.php /tmp/scsv.php /tmp/scsvm.php /tmp/scsvp.py
# Si la cola de correos corre como servicio, se reinicia para que use el código nuevo
php artisan queue:restart >/dev/null 2>&1
echo LISTO
