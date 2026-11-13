#!/bin/bash
# Ventas de hoy y futuras que aparecen cerradas sin cerrarse. Sin argumento: diagnóstico. Con "arreglar": instala el arreglo y las reabre.
cd /var/www/html/botacurapp || exit 1
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
if [ "$1" = "arreglar" ]; then
  TS=$(date +%Y%m%d%H%M)
  FS="app/Http/Controllers/Api/BotReservaController.php app/Http/Controllers/Api/BotController.php $(ls app/Http/Controllers/*ReservasDelDia*Controller.php 2>/dev/null)"
  for f in $FS; do sudo cp $f $f.bak.$TS; done
  curl -fsSL $U/bot/patch_venta_abierta_1211.py -o /tmp/pva.py && sudo python3 /tmp/pva.py; rm -f /tmp/pva.py
  for f in $FS; do php -l $f >/dev/null || { for g in $FS; do sudo cp $g.bak.$TS $g; done; echo "ERROR de sintaxis: restaurado"; exit 1; }; done
fi
curl -fsSL $U/bot/ventas_abiertas_1311.php -o /tmp/va.php && sudo -u www-data php /tmp/va.php "$@" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/va.php
sudo -u www-data php artisan cache:clear >/dev/null; sudo -u www-data php artisan view:clear >/dev/null
echo LISTO
