#!/bin/bash
# Poro Poro (solo administradores): botón "Crear producto" con formulario en ventana (modal) y botón
# "Ver y editar productos" que lleva a la lista donde se cambian nombres y valores.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/poro
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
P=resources/views/themes/backoffice/pages/poroporo
NUEVOS="app/Http/Controllers/PoroProductosController.php routes/poroporo_productos.php $P/includes/_productos_editor.blade.php $P/includes/_crear_producto.blade.php $P/index.blade.php"
EDIT="$P/venta/index.blade.php $P/venta/index_admin.blade.php $P/venta/create.blade.php routes/web.php app/Http/Controllers/PoroPoroController.php"
for f in $EDIT $NUEVOS; do [ -f "$f" ] && cp "$f" "$f.bak.$TS"; done
for f in $NUEVOS; do mkdir -p "$(dirname $f)"; curl -fsSL "$U/$f" -o "$f.nuevo" || { echo "ERROR bajando $f"; exit 1; }; done
for f in $NUEVOS; do mv "$f.nuevo" "$f"; done
curl -fsSL $U/patch.py -o /tmp/poro_patch.py || exit 1
if ! python3 /tmp/poro_patch.py "$PWD" || ! php -l app/Http/Controllers/PoroProductosController.php >/dev/null || ! php -l app/Http/Controllers/PoroPoroController.php >/dev/null || ! php -l routes/web.php >/dev/null; then
  for f in $EDIT $NUEVOS; do [ -f "$f.bak.$TS" ] && cp "$f.bak.$TS" "$f"; done; echo "ERROR: restaurado"; exit 1
fi
rm -f /tmp/poro_patch.py
php artisan route:clear >/dev/null; php artisan view:clear >/dev/null
php artisan route:list 2>/dev/null | grep -c poroporo_productos | sed 's/^/Rutas de edición: /'
echo "LISTO: Poro Poro › Crear producto / Ver y editar productos"
