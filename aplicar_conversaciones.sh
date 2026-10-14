#!/bin/bash
# Pagina "Conversaciones del bot" (listado de Aprendizajes) y tarjeta del inicio apuntando a ella
cd /var/www/html/botacurapp || exit 1
rm -rf public/rev-407c2cbc40ed   # copia temporal de lectura
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/conversaciones
C=app/Http/Controllers/BotConversacionesController.php
V=resources/views/themes/backoffice/pages/bot-conversaciones/index.blade.php
mkdir -p $(dirname $V)
[ -f $C ] && cp $C $C.bak.$(date +%Y%m%d%H%M%S); [ -f $V ] && cp $V $V.bak.$(date +%Y%m%d%H%M%S)
curl -fsSL "$U/BotConversacionesController.php?v=$(date +%s)" -o $C || exit 1
curl -fsSL "$U/index.blade.php?v=$(date +%s)" -o $V || exit 1
curl -fsSL "$U/instalar.py?v=$(date +%s)" -o /tmp/instalar_conversaciones.py || exit 1
grep -q conversacionesRecientes $C || { echo "Descargo una version antigua, intenta en 2 minutos"; exit 1; }
php -l $C || { rm -f $C $V; echo "ERROR en el controlador; no se instalo"; exit 1; }
python3 /tmp/instalar_conversaciones.py || exit 1
php -l routes/web.php || { cp "$(ls routes/web.php.bak.* | sort | tail -1)" routes/web.php; echo "ERROR en rutas: restauradas"; exit 1; }
php artisan view:clear >/dev/null; php artisan route:clear >/dev/null
for f in $V resources/views/themes/backoffice/pages/admin/show.blade.php; do
  php artisan tinker --execute="echo strlen(app('blade.compiler')->compileString(file_get_contents('$f'))) > 0 ? 'Vista OK: $(basename $(dirname $f))' : 'Vista vacia: $f';" 2>&1 | tail -1
done
php artisan route:list 2>/dev/null | grep -c "bot-conversaciones" | sed 's/^/Ruta: /'
echo "LISTO: https://app.botacura.cl/bot-conversaciones"
