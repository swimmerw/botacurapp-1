#!/bin/bash
# BotacurApp (AWS): turnos fijos por dia (Elohim lun-vie, Camila mar-sab, Fabiola lun y jue, Juan Carlos sab-dom). 17/10/2026
set -e
C=${1:?falta el commit}
cd /var/www/html/botacurapp
[ -f config/turnos.php ] || { echo "Primero instala reporte_manana.sh"; exit 1; }
P=/tmp/turnos_fijos.patch
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$C/turnos_fijos.patch" -o $P
if git apply --reverse --check $P 2>/dev/null; then echo "Ya estaba aplicado."; else
  git apply --check $P 2>/tmp/tf_check.txt || { echo "No calza (no se cambio nada):"; cat /tmp/tf_check.txt; exit 1; }
  cp -p config/turnos.php config/turnos.php.bak.$(date +%Y%m%d%H%M%S)
  git apply $P
fi
php -l config/turnos.php >/dev/null || { git apply -R $P; echo "Error, revertido"; exit 1; }
php artisan config:clear >/dev/null
echo "--- Vista previa para mañana (no se envia nada):"
php artisan botacura:reporte-manana --probar
echo "LISTO: turnos fijos actualizados."
