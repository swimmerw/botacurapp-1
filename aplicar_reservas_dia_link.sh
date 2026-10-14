#!/bin/bash
# Reservas del dia: el N de reserva abre la reserva
cd /var/www/html/botacurapp || exit 1
V=resources/views/themes/backoffice/pages/reporte/reservas_dia.blade.php
cp $V $V.bak.$(date +%Y%m%d%H%M%S)
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/reservas_dia/reservas_dia.blade.php?v=$(date +%s)" -o /tmp/reservas_dia.blade.php || exit 1
grep -q "backoffice.reserva.show" /tmp/reservas_dia.blade.php || { echo "ERROR: descargo una version antigua, intenta en 2 minutos"; exit 1; }
cp /tmp/reservas_dia.blade.php $V
php artisan view:clear >/dev/null
R=$(php artisan tinker --execute="echo strlen(app('blade.compiler')->compileString(file_get_contents('$V'))) > 0 ? 'Vista OK' : 'Vista vacia';" 2>&1 | tail -1)
echo "$R"
[ "$R" = "Vista OK" ] || { cp "$(ls $V.bak.* | sort | tail -1)" $V; php artisan view:clear >/dev/null; echo "Vista restaurada"; exit 1; }
php artisan route:list 2>/dev/null | grep -c "backoffice.reserva.show" | sed 's/^/Ruta de la reserva: /'
echo LISTO
