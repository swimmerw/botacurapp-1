#!/bin/bash
# Poro Poro: crear productos y editar nombre/valor en la misma vista de Ventas Poro Poro (admin y jefe de local).
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/poro
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
NUEVOS="app/Http/Controllers/PoroProductosController.php routes/poroporo_productos.php resources/views/themes/backoffice/pages/poroporo/includes/_productos_editor.blade.php"
V=resources/views/themes/backoffice/pages/poroporo/venta
EDIT="$V/index.blade.php $V/index_admin.blade.php $V/create.blade.php routes/web.php"
for f in $EDIT; do cp "$f" "$f.bak.$TS"; done
for f in $NUEVOS; do mkdir -p "$(dirname $f)"; curl -fsSL "$U/$f" -o "$f" || { echo "ERROR bajando $f"; exit 1; }; done
curl -fsSL $U/patch.py -o /tmp/poro_patch.py || exit 1
if ! python3 /tmp/poro_patch.py "$PWD" || ! php -l app/Http/Controllers/PoroProductosController.php >/dev/null || ! php -l routes/web.php >/dev/null; then
  for f in $EDIT; do cp "$f.bak.$TS" "$f"; done; echo "ERROR: restaurado"; exit 1
fi
rm -f /tmp/poro_patch.py
php artisan route:clear >/dev/null; php artisan view:clear >/dev/null
php artisan route:list 2>/dev/null | grep -c poroporo_productos | sed 's/^/Rutas nuevas: /'
echo "LISTO: entra a Poro Poro en el menú"
