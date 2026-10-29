#!/bin/bash
# Editar la diferencia de una venta (solo administradores): antes del cierre (diferencia por pagar,
# la que usa el cierre y la boleta) y después (monto y medio de pago). Se puede correr de nuevo.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}
echo "Version: ${SHA:0:7}"
T=$(date +%Y%m%d%H%M)
V=resources/views/themes/backoffice/pages/reserva/includes/venta.blade.php
C=resources/views/themes/backoffice/pages/venta/cerrar.blade.php
sudo cp routes/web.php routes/web.php.bak.$T; sudo cp $V $V.bak.$T; sudo cp $C $C.bak.$T
restaurar(){ echo "ERROR: se restauran los respaldos"; sudo cp routes/web.php.bak.$T routes/web.php; sudo cp $V.bak.$T $V; sudo cp $C.bak.$T $C; exit 1; }
MIG=database/migrations/2026_10_29_000001_create_venta_diferencia_cambios_table.php
MIG2=database/migrations/2026_10_29_000002_add_campo_to_venta_diferencia_cambios_table.php
curl -fsSL $R/diferencia/VentaDiferenciaController.php -o /tmp/vdc.php || exit 1
curl -fsSL $R/diferencia/2026_10_29_000001_create_venta_diferencia_cambios_table.php -o /tmp/vdm.php || exit 1
curl -fsSL $R/diferencia/2026_10_29_000002_add_campo_to_venta_diferencia_cambios_table.php -o /tmp/vdm2.php || exit 1
curl -fsSL $R/diferencia/modal_editar_diferencia.blade.php -o /tmp/vdv.php || exit 1
curl -fsSL $R/diferencia/patch.py -o /tmp/vdp.py || exit 1
sudo cp /tmp/vdc.php app/Http/Controllers/VentaDiferenciaController.php
sudo cp /tmp/vdm.php $MIG
sudo cp /tmp/vdm2.php $MIG2
sudo cp /tmp/vdv.php resources/views/themes/backoffice/pages/reserva/includes/modal_editar_diferencia.blade.php
sudo chown www-data:www-data app/Http/Controllers/VentaDiferenciaController.php $MIG $MIG2 resources/views/themes/backoffice/pages/reserva/includes/modal_editar_diferencia.blade.php
sudo python3 /tmp/vdp.py "$PWD" || restaurar
php -l app/Http/Controllers/VentaDiferenciaController.php >/dev/null && php -l routes/web.php >/dev/null || restaurar
sudo -u www-data php artisan migrate --path=$MIG --force 2>&1 | grep -v Deprecated
sudo -u www-data php artisan migrate --path=$MIG2 --force 2>&1 | grep -v Deprecated
sudo -u www-data php artisan route:clear >/dev/null; sudo -u www-data php artisan view:clear >/dev/null
sudo -u www-data php artisan route:list 2>/dev/null | grep -q venta.diferencia.update && echo "Ruta OK" || echo "OJO: no veo la ruta"
rm -f /tmp/vdc.php /tmp/vdm.php /tmp/vdm2.php /tmp/vdv.php /tmp/vdp.py
echo LISTO
