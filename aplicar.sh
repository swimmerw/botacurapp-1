#!/bin/bash
# Parche finanzas Botacura (13/10/2026). Uso: cd /var/www/html/botacurapp && curl -fsSL <url>/aplicar.sh | bash
set -e
cd /var/www/html/botacurapp
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
curl -fsSL "$R/finanzas.py" -o /tmp/finanzas.py
curl -fsSL "$R/anual.blade.php" -o /tmp/anual.blade.php
python3 /tmp/finanzas.py
if ! php -l app/Http/Controllers/ReporteFinancieroController.php; then
  echo "ERROR de sintaxis: restaurando respaldos"
  for f in $(ls app/Http/Controllers/ReporteFinancieroController.php.bak.* resources/views/themes/backoffice/pages/reporte/financiero/*.bak.* resources/views/themes/backoffice/pages/reporte/ingreso/percibido.blade.php.bak.* | sort -t. -k1 | tail -4); do cp "$f" "${f%.bak.*}"; done
  exit 1
fi
php artisan view:clear
rm -rf public/rev-* 2>/dev/null || true
echo "LISTO: revisa /finanzas/utilidad, /finanzas/resumen-anual y /finanzas/ingresos"
