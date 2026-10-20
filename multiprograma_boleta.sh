#!/bin/bash
# BotacurApp (AWS): varios programas - desayuno/once por programa y detalle en la boleta. 15/10/2026
# Uso: ... | bash -s <commit>
set -e
C=${1:?falta el commit}
cd /var/www/html/botacurapp
P=/tmp/multiprograma_boleta.patch
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$C/multiprograma_boleta.patch" -o $P
if git apply --reverse --check $P 2>/dev/null; then echo "Ya estaba aplicado."; else
  git apply --check $P 2>/tmp/boleta_check.txt || { echo "No calza con el codigo del servidor (no se cambio nada):"; cat /tmp/masajes_check.txt; exit 1; }
  T=$(date +%Y%m%d%H%M%S)
  git diff --name-only HEAD > /dev/null
  tar czf ~/respaldo_multiprograma_boleta_$T.tgz $(grep -E '^\+\+\+ b/' $P | sed 's#^+++ b/##' | while read f; do [ -f "$f" ] && echo "$f"; done)
  echo "Respaldo: ~/respaldo_multiprograma_boleta_$T.tgz"
  git apply $P
fi
for f in app/Reserva.php app/Services/DesayunoOnceService.php app/Http/Controllers/MenuController.php app/Http/Controllers/ReservaController.php app/Jobs/ProcesarVentaCerrada.php; do
  php -l $f >/dev/null || { echo "Error de sintaxis en $f: se revierte"; git apply -R $P; exit 1; }
done
php artisan view:clear >/dev/null; php artisan route:clear >/dev/null; php artisan config:clear >/dev/null
php artisan queue:restart >/dev/null 2>&1 || true
echo "LISTO: desayuno/once por programa y detalle de programas en la boleta."
