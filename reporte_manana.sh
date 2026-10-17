#!/bin/bash
# BotacurApp (AWS): reporte del dia siguiente a las 15:00 con turno sugerido y margen. 17/10/2026
# Uso: ... | bash -s <commit>
set -e
C=${1:?falta el commit}
cd /var/www/html/botacurapp
P=/tmp/reporte_manana.patch
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$C/reporte_manana.patch" -o $P
if git apply --reverse --check $P 2>/dev/null; then echo "Ya estaba aplicado."; else
  git apply --check $P 2>/tmp/rm_check.txt || { echo "No calza con el codigo del servidor (no se cambio nada):"; cat /tmp/rm_check.txt; exit 1; }
  T=$(date +%Y%m%d%H%M%S)
  tar czf ~/respaldo_reporte_manana_$T.tgz $(grep -E '^\+\+\+ b/' $P | sed 's#^+++ b/##' | while read f; do [ -f "$f" ] && echo "$f"; done) 2>/dev/null || true
  git apply $P
fi
for f in app/Services/TurnosService.php app/Console/Commands/ReporteManana.php app/Console/Kernel.php config/turnos.php; do
  php -l $f >/dev/null || { echo "Error de sintaxis en $f: se revierte"; git apply -R $P; exit 1; }
done
php artisan migrate --path=database/migrations/2026_10_17_000001_create_turnos_sugeridos_table.php --force \
  || { echo "Fallo la tabla: se revierte"; git apply -R $P; exit 1; }
php artisan config:clear >/dev/null
echo "--- Cron del programador de tareas:"
crontab -l 2>/dev/null | grep -i "schedule:run" || sudo crontab -l -u www-data 2>/dev/null | grep -i "schedule:run" || echo "AVISO: no veo 'schedule:run' en el cron (las tareas programadas no correrian)"
php artisan schedule:list 2>/dev/null | grep -i reporte-manana || true
echo "--- Vista previa para mañana (no se envia nada):"
php artisan botacura:reporte-manana --probar
echo "LISTO: el reporte saldra todos los dias a las 15:00 (si hay reservas al dia siguiente)."
