#!/bin/bash
# Diagnostico 2: descuadre real descontando propinas (solo lectura)
cd /var/www/html/botacurapp
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
Q() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$1" 2>/dev/null; }
V="SELECT v.id venta, r.fecha_visita, LEFT(c.nombre_cliente,22) cliente,
  COALESCE(v.diferencia_programa,0) dif,
  COALESCE((SELECT SUM(dc.subtotal) FROM consumos co JOIN detalles_consumos dc ON dc.id_consumo=co.id WHERE co.id_venta=v.id),0) cons,
  COALESCE((SELECT ROUND(SUM(dc.subtotal)*0.1) FROM consumos co JOIN detalles_consumos dc ON dc.id_consumo=co.id WHERE co.id_venta=v.id AND dc.genera_propina=1),0) propina,
  COALESCE((SELECT SUM(co.total_consumo) FROM consumos co WHERE co.id_venta=v.id),0) total_consumo,
  COALESCE((SELECT SUM(ds.subtotal) FROM consumos co JOIN detalle_servicios_extra ds ON ds.id_consumo=co.id WHERE co.id_venta=v.id),0) serv,
  COALESCE((SELECT SUM(COALESCE(pc.pago1,0)+COALESCE(pc.pago2,0)) FROM pago_consumos pc WHERE pc.id_venta=v.id),0) pagado,
  (SELECT SUM(pc.id_tipo_transaccion1 IS NULL) FROM pago_consumos pc WHERE pc.id_venta=v.id) pago_sin_medio
FROM ventas v JOIN reservas r ON r.id=v.id_reserva LEFT JOIN clientes c ON c.id=r.cliente_id"
echo "=== 1. Por mes: descuadre real (pagado - diferencia - consumos - propina - servicios) ==="
Q "SELECT DATE_FORMAT(fecha_visita,'%Y-%m') mes, SUM(propina) propinas, SUM(pagado-dif-cons-propina-serv) descuadre_real,
  SUM(ABS(pagado-dif-cons-propina-serv)>1000) ventas_con_descuadre FROM ($V) x
WHERE fecha_visita BETWEEN '2026-01-01' AND CURDATE() GROUP BY mes ORDER BY mes;"
echo "=== 2. Octubre: ventas con descuadre real mayor a 1.000 ==="
Q "SELECT x.*, pagado-dif-cons-propina-serv descuadre FROM ($V) x
WHERE fecha_visita BETWEEN '2026-10-01' AND CURDATE() HAVING ABS(descuadre)>1000 ORDER BY fecha_visita;"
echo "=== 3. Octubre: abonos sin medio de pago y pagos sin medio de pago ==="
Q "SELECT 'abono sin medio' tipo, COUNT(*) n, SUM(v.abono_programa) monto FROM ventas v JOIN reservas r ON r.id=v.id_reserva
 WHERE r.created_at >= '2026-10-01' AND v.abono_programa>0 AND (v.id_tipo_transaccion_abono IS NULL OR v.id_tipo_transaccion_abono NOT IN (SELECT id FROM tipos_transacciones))
UNION ALL SELECT 'pago sin medio', COUNT(*), SUM(COALESCE(pc.pago1,0)+COALESCE(pc.pago2,0)) FROM pago_consumos pc JOIN ventas v ON v.id=pc.id_venta JOIN reservas r ON r.id=v.id_reserva
 WHERE r.fecha_visita BETWEEN '2026-10-01' AND CURDATE() AND pc.id_tipo_transaccion1 IS NULL
UNION ALL SELECT 'pago2 sin medio', COUNT(*), SUM(pc.pago2) FROM pago_consumos pc JOIN ventas v ON v.id=pc.id_venta JOIN reservas r ON r.id=v.id_reserva
 WHERE r.fecha_visita BETWEEN '2026-10-01' AND CURDATE() AND pc.pago2>0 AND pc.id_tipo_transaccion2 IS NULL;"
