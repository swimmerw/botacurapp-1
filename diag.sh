#!/bin/bash
# Diagnostico de descuadre de caja y Gift Cards (solo lectura)
cd /var/www/html/botacurapp
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
Q() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$1" 2>/dev/null; }
echo "=== 1. Descuadre por mes (diferencia+consumos+servicios cobrados vs pagos registrados) ==="
Q "SELECT DATE_FORMAT(r.fecha_visita,'%Y-%m') mes, COUNT(*) ventas_con_descuadre, SUM(x.cobrar - x.pagado) descuadre
FROM (SELECT v.id, v.id_reserva,
  COALESCE(v.diferencia_programa,0)
  + COALESCE((SELECT SUM(dc.subtotal) FROM consumos co JOIN detalles_consumos dc ON dc.id_consumo=co.id WHERE co.id_venta=v.id),0)
  + COALESCE((SELECT SUM(ds.subtotal) FROM consumos co JOIN detalle_servicios_extra ds ON ds.id_consumo=co.id WHERE co.id_venta=v.id),0) cobrar,
  COALESCE((SELECT SUM(COALESCE(pc.pago1,0)+COALESCE(pc.pago2,0)) FROM pago_consumos pc WHERE pc.id_venta=v.id),0) pagado
  FROM ventas v) x JOIN reservas r ON r.id=x.id_reserva
WHERE r.fecha_visita BETWEEN '2026-01-01' AND CURDATE() AND x.cobrar<>x.pagado
GROUP BY mes ORDER BY mes;"
echo "=== 2. Detalle octubre (cada venta que no cuadra) ==="
Q "SELECT r.fecha_visita, v.id venta, r.id reserva, LEFT(c.nombre_cliente,25) cliente,
  COALESCE(v.diferencia_programa,0) dif,
  COALESCE((SELECT SUM(dc.subtotal) FROM consumos co JOIN detalles_consumos dc ON dc.id_consumo=co.id WHERE co.id_venta=v.id),0) cons,
  COALESCE((SELECT SUM(ds.subtotal) FROM consumos co JOIN detalle_servicios_extra ds ON ds.id_consumo=co.id WHERE co.id_venta=v.id),0) serv,
  COALESCE((SELECT SUM(COALESCE(pc.pago1,0)+COALESCE(pc.pago2,0)) FROM pago_consumos pc WHERE pc.id_venta=v.id),0) pagado,
  (SELECT COUNT(*) FROM pago_consumos pc WHERE pc.id_venta=v.id) n_pagos,
  (SELECT MAX(pc.valor_consumo) FROM pago_consumos pc WHERE pc.id_venta=v.id) valor_consumo,
  v.total_pagar pendiente,
  (SELECT COUNT(*) FROM gift_cards g WHERE g.id_venta=v.id) gc
FROM ventas v JOIN reservas r ON r.id=v.id_reserva LEFT JOIN clientes c ON c.id=r.cliente_id
WHERE r.fecha_visita BETWEEN '2026-10-01' AND CURDATE()
HAVING dif+cons+serv <> pagado ORDER BY r.fecha_visita;"
echo "=== 3. Gift Cards 2026 ==="
Q "SELECT g.id, g.codigo, g.monto, DATE(g.created_at) vendida, g.usada, g.fecha_uso, g.id_venta,
  v.abono_programa abono_venta, tt.nombre tipo_abono, v.diferencia_programa dif_venta
FROM gift_cards g LEFT JOIN ventas v ON v.id=g.id_venta LEFT JOIN tipos_transacciones tt ON tt.id=v.id_tipo_transaccion_abono
WHERE g.created_at >= '2026-01-01' ORDER BY g.created_at;"
echo "=== 4. Columnas de gift_cards y tipos de transaccion ==="
Q "SHOW COLUMNS FROM gift_cards; SELECT id, nombre FROM tipos_transacciones;"
