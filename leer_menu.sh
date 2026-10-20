#!/bin/bash
# Solo lectura: inicio (cuadro Administracion), menu lateral y rutas del bot. Sin datos de clientes.
cd /var/www/html/botacurapp
D=public/rev-67c771d20bf5; mkdir -p $D
cp resources/views/themes/backoffice/pages/admin/show.blade.php $D/inicio.txt
cp resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php $D/menu.txt
php artisan route:list 2>/dev/null | grep -iE "bot|conversa" > $D/rutas.txt
chmod 644 $D/*.txt; ls -la $D; echo LISTO
