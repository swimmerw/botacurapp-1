#!/bin/bash
# Reservas del bot con la venta abierta (como las de la app) y reapertura de las de hoy/futuras. 12/11/2026
cd /var/www/html/botacurapp || exit 1
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
TS=$(date +%Y%m%d%H%M)
FS="app/Http/Controllers/Api/BotReservaController.php app/Http/Controllers/Api/BotController.php $(ls app/Http/Controllers/*ReservasDelDia*Controller.php 2>/dev/null)"
for f in $FS; do sudo cp $f $f.bak.$TS; done
R() { for f in $FS; do sudo cp $f.bak.$TS $f; done; echo "ERROR: $1. Restaurado, no cambió nada."; exit 1; }
curl -fsSL $U/bot/patch_venta_abierta_1211.py -o /tmp/pva.py && sudo python3 /tmp/pva.py || R "el parche no calzó"; rm -f /tmp/pva.py
for f in $FS; do php -l $f >/dev/null || R "sintaxis en $f"; done
curl -fsSL $U/bot/ventas_abiertas_1211.php -o /tmp/va.php && sudo -u www-data php /tmp/va.php "$@" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/va.php
sudo -u www-data php artisan cache:clear >/dev/null; sudo -u www-data php artisan view:clear >/dev/null
echo LISTO
