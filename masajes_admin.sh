#!/bin/bash
# BotacurApp (AWS): listado de masajes editable por el administrador (editar y borrar). 15/10/2026
# Uso: ... | bash -s <commit>
set -e
C=${1:?falta el commit}
cd /var/www/html/botacurapp
P=/tmp/masajes_admin.patch
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$C/masajes_admin.patch" -o $P
if git apply --reverse --check $P 2>/dev/null; then echo "Ya estaba aplicado."; else
  git apply --check $P 2>/tmp/masajes_check.txt || { echo "No calza con el codigo del servidor (no se cambio nada):"; cat /tmp/masajes_check.txt; exit 1; }
  T=$(date +%Y%m%d%H%M%S)
  git diff --name-only HEAD > /dev/null
  tar czf ~/respaldo_masajes_admin_$T.tgz $(grep -E '^\+\+\+ b/' $P | sed 's#^+++ b/##' | while read f; do [ -f "$f" ] && echo "$f"; done)
  echo "Respaldo: ~/respaldo_masajes_admin_$T.tgz"
  git apply $P
fi
for f in app/Http/Controllers/MasajeController.php routes/web.php; do
  php -l $f >/dev/null || { echo "Error de sintaxis en $f: se revierte"; git apply -R $P; exit 1; }
done
php artisan view:clear >/dev/null; php artisan route:clear >/dev/null; php artisan config:clear >/dev/null
php artisan route:list 2>/dev/null | grep -q "masaje.admin_update" || echo "AVISO: no veo la ruta masaje.admin_update"
echo "LISTO: en Masajes, el administrador ve Editar y Borrar en cada fila."
