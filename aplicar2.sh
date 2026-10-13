#!/bin/bash
set -e
cd /var/www/html/botacurapp
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/finanzas2.py -o /tmp/finanzas2.py
python3 /tmp/finanzas2.py
if ! php -l app/Http/Controllers/ReporteFinancieroController.php; then
  cp "$(ls app/Http/Controllers/ReporteFinancieroController.php.bak.* | sort | tail -1)" app/Http/Controllers/ReporteFinancieroController.php
  echo "ERROR de sintaxis: restaurado"; exit 1
fi
echo "LISTO"
