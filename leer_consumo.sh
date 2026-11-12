#!/bin/bash
# Solo lectura: por qué las reservas del bot aparecen sin consumo / venta cerrada en Gestión Consumo (12/11/2026)
cd /var/www/html/botacurapp || exit 1
D=public/rev-c4a4fa2759c0; mkdir -p $D
V=$(grep -rl "Gestión Consumo\|Gestion Consumo" resources/views | grep -v "\.bak" | head -3)
for f in $V; do cp "$f" $D/vista_$(basename "$f" .blade.php).txt; done
RT=$(grep -n "consumo" routes/web.php | grep -i "gesti\|index" | head -5); echo "$RT" > $D/rutas.txt
C=$(echo "$RT" | grep -o "[A-Za-z]*Controller" | head -2)
for c in $C; do f=$(find app/Http/Controllers -name "$c.php" | head -1); [ -n "$f" ] && cp "$f" $D/$c.txt; done
for f in app/Http/Controllers/VentaController.php app/Http/Controllers/ReservaController.php app/Venta.php app/Reserva.php; do [ -f $f ] && cp $f $D/$(basename $f .php).txt; done
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT r.id, r.fuente, r.estado, r.user_id, r.created_at, v.* , (SELECT COUNT(*) FROM consumos c WHERE c.id_venta=v.id) consumos FROM reservas r LEFT JOIN ventas v ON v.id_reserva=r.id WHERE DATE(r.fecha_visita)=CURDATE()\G" 2>/dev/null | grep -v "comprobante_\|_url\|token" > $D/hoy.txt
chmod 644 $D/*.txt; ls $D; echo LISTO
