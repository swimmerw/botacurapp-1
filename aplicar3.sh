#!/bin/bash
set -e
cd /var/www/html/botacurapp
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/finanzas3.py -o /tmp/finanzas3.py
python3 /tmp/finanzas3.py
if ! php -l app/Http/Controllers/ReporteFinancieroController.php; then
  cp "$(ls app/Http/Controllers/ReporteFinancieroController.php.bak.* | sort | tail -1)" app/Http/Controllers/ReporteFinancieroController.php
  cp "$(ls resources/views/themes/backoffice/pages/reporte/ingreso/percibido.blade.php.bak.* | sort | tail -1)" resources/views/themes/backoffice/pages/reporte/ingreso/percibido.blade.php
  echo "ERROR de sintaxis: restaurado"; exit 1
fi
php artisan view:clear
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
echo "=== Abonos de octubre sin medio de pago ==="
mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT r.id reserva, v.id venta, DATE(r.created_at) creada, r.fecha_visita, LEFT(c.nombre_cliente,25) cliente, v.abono_programa, v.id_tipo_transaccion_abono FROM ventas v JOIN reservas r ON r.id=v.id_reserva LEFT JOIN clientes c ON c.id=r.cliente_id WHERE r.created_at>='2026-10-01' AND v.abono_programa>0 AND (v.id_tipo_transaccion_abono IS NULL OR v.id_tipo_transaccion_abono NOT IN (SELECT id FROM tipos_transacciones));" 2>/dev/null
echo "LISTO"
