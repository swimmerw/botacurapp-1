#!/bin/bash
# BotacurApp (AWS): reservas con varios programas + menus/masajes ajustados a las personas. 15/10/2026
# Uso: ... | bash -s <commit>
set -e
C=${1:?falta el commit}
cd /var/www/html/botacurapp
P=/tmp/reserva_multiprograma.patch
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$C/reserva_multiprograma.patch" -o $P
if git apply --reverse --check $P 2>/dev/null; then echo "Ya estaba aplicado."; else
  git apply --check $P 2>/tmp/multi_check.txt || { echo "No calza con el codigo del servidor (no se cambio nada):"; cat /tmp/multi_check.txt; exit 1; }
  T=$(date +%Y%m%d%H%M%S)
  git diff --name-only HEAD > /dev/null
  tar czf ~/respaldo_multiprograma_$T.tgz $(grep -E '^\+\+\+ b/' $P | sed 's#^+++ b/##' | while read f; do [ -f "$f" ] && echo "$f"; done)
  echo "Respaldo: ~/respaldo_multiprograma_$T.tgz"
  git apply $P
fi
for f in app/Reserva.php app/ReservaPrograma.php app/Services/ReservaProgramasService.php app/Http/Controllers/ReservaController.php app/Http/Controllers/VisitaController.php; do
  php -l $f >/dev/null || { echo "Error de sintaxis en $f: se revierte"; git apply -R $P; exit 1; }
done
php artisan migrate --path=database/migrations/2026_10_15_000001_create_reserva_programas_table.php --force \
  || { echo "Fallo la tabla: se revierte"; git apply -R $P; exit 1; }
php artisan view:clear >/dev/null; php artisan config:clear >/dev/null
echo "LISTO: reservas con varios programas y menus/masajes ajustados a la cantidad de personas."
