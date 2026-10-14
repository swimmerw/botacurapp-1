#!/bin/bash
# Inicio > Administracion: Reservas del dia, Configuracion del bot y Conversaciones; icono del bot en el menu
cd /var/www/html/botacurapp || exit 1
rm -rf public/rev-67c771d20bf5   # copia temporal de lectura
V=resources/views/themes/backoffice/pages/admin/show.blade.php
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/menu_admin.py -o /tmp/menu_admin.py || exit 1
python3 /tmp/menu_admin.py || exit 1
php artisan view:clear >/dev/null
R=$(php artisan tinker --execute="echo strlen(app('blade.compiler')->compileString(file_get_contents('$V'))) > 0 ? 'Vista OK' : 'Vista vacia';" 2>&1 | tail -1)
echo "$R"
if [ "$R" != "Vista OK" ]; then cp "$(ls $V.bak.* | sort | tail -1)" $V; php artisan view:clear >/dev/null; echo "Inicio restaurado"; exit 1; fi
echo "LISTO"
