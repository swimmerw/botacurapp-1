#!/bin/bash
# Fecha de abono real en ventas + correccion de 3 abonos sin medio de pago (13/10/2026)
set -e
cd /var/www/html/botacurapp
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
M() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$1" 2>/dev/null; }
TS=$(date +%Y%m%d%H%M)
mysqldump -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --no-tablespaces "$DB_DATABASE" ventas 2>/dev/null > ~/respaldo_ventas_$TS.sql
echo "Respaldo de ventas: ~/respaldo_ventas_$TS.sql ($(wc -c < ~/respaldo_ventas_$TS.sql) bytes)"
if [ "$(M "SHOW COLUMNS FROM ventas LIKE 'fecha_abono';" | wc -l)" = "0" ]; then
  M "ALTER TABLE ventas ADD COLUMN fecha_abono DATE NULL AFTER abono_programa;"
  M "UPDATE ventas v JOIN reservas r ON r.id = v.id_reserva SET v.fecha_abono = DATE(r.created_at) WHERE v.fecha_abono IS NULL;"
  echo "Columna fecha_abono creada y llenada con la fecha de creacion de cada reserva"
fi
# Datos confirmados por Sebastian (13/10)
M "UPDATE ventas SET id_tipo_transaccion_abono = 4, fecha_abono = '2026-10-08' WHERE id = 2610 AND id_reserva = 2611;"
M "UPDATE ventas SET id_tipo_transaccion_abono = 2, fecha_abono = '2026-10-08' WHERE id = 2612 AND id_reserva = 2613;"
M "UPDATE ventas SET id_tipo_transaccion_abono = 4, fecha_abono = '2026-10-12' WHERE id = 2624 AND id_reserva = 2625;"
M "SELECT v.id venta, v.id_reserva reserva, v.abono_programa, v.fecha_abono, t.nombre medio FROM ventas v LEFT JOIN tipos_transacciones t ON t.id = v.id_tipo_transaccion_abono WHERE v.id IN (2610, 2612, 2624);"
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/finanzas4.py -o /tmp/finanzas4.py
python3 /tmp/finanzas4.py
if ! php -l app/Http/Controllers/ReporteFinancieroController.php; then
  cp "$(ls app/Http/Controllers/ReporteFinancieroController.php.bak.* | sort | tail -1)" app/Http/Controllers/ReporteFinancieroController.php
  echo "ERROR de sintaxis: codigo restaurado (la columna y los datos quedan)"; exit 1
fi
php artisan view:clear
echo "LISTO"
