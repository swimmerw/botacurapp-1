#!/bin/bash
# Vista "Reservas del dia" con suma de abonos (13/10/2026)
cd /var/www/html/botacurapp || exit 1
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/reservas_dia
C=app/Http/Controllers/ReservasDelDiaController.php
V=resources/views/themes/backoffice/pages/reporte/reservas_dia.blade.php
curl -fsSL $U/ReservasDelDiaController.php -o $C || exit 1
curl -fsSL $U/reservas_dia.blade.php -o $V || exit 1
curl -fsSL $U/instalar.py -o /tmp/instalar_reservas_dia.py || exit 1
php -l $C || { rm -f $C $V; echo "ERROR en el controlador; no se instalo"; exit 1; }
python3 /tmp/instalar_reservas_dia.py || exit 1
php -l routes/web.php || { cp "$(ls routes/web.php.bak.* | sort | tail -1)" routes/web.php; echo "ERROR en rutas: restauradas"; exit 1; }
php artisan view:clear >/dev/null; php artisan route:clear >/dev/null
# Compilar la vista para detectar errores de Blade
php artisan tinker --execute="echo strlen(app('blade.compiler')->compileString(file_get_contents('$V'))) > 0 ? 'Vista OK' : 'Vista vacia';" 2>&1 | tail -1
php artisan route:list 2>/dev/null | grep -c reservas-del-dia | sed 's/^/Rutas encontradas: /'
echo "LISTO: Finanzas > Reservas del dia (https://app.botacura.cl/finanzas/reservas-del-dia)"
