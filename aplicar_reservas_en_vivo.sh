#!/bin/bash
# Vista "Reservas en vivo" (10/11/2026): reservas que se van creando con vista previa del comprobante.
cd /var/www/html/botacurapp || exit 1
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/envivo
V=resources/views/themes/backoffice/pages/reservas-en-vivo
sudo mkdir -p $V
sudo curl -fsSL $R/ReservasEnVivoController.php -o app/Http/Controllers/ReservasEnVivoController.php || exit 1
sudo curl -fsSL $R/index.blade.php -o $V/index.blade.php || exit 1
curl -fsSL $R/instalar.py -o /tmp/rev_instalar.py && sudo python3 /tmp/rev_instalar.py || exit 1; rm -f /tmp/rev_instalar.py
php -l app/Http/Controllers/ReservasEnVivoController.php >/dev/null && php -l routes/web.php >/dev/null || { echo "ERROR de sintaxis"; exit 1; }
sudo -u www-data php artisan view:clear >/dev/null; sudo -u www-data php artisan route:clear >/dev/null
php artisan route:list 2>/dev/null | grep -o "[^ ]*reservas-en-vivo " | head -1 | sed 's/^/Ruta OK: \//'
echo "LISTO"
