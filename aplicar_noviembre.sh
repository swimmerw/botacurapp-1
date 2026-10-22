#!/bin/bash
# Noviembre: programas y precios del afiche, lunes a domingo, 10% lun–jue (programas S), mínimo 10 personas lun–jue,
# grupos +15 con cambios a 10 días, Botacura Full disponible, y sincroniza la web.
# Uso: ... | bash                -> instala el código y MUESTRA los cambios de datos (no los aplica)
#      ... | bash -s -- --aplicar -> además aplica los datos y actualiza la web
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/nov
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
F="app/Services/BotTarifasService.php app/Services/BotPromptService.php"
for f in $F; do cp "$f" "$f.bak.$TS"; done
curl -fsSL $R/patch_codigo.py -o /tmp/nov_codigo.py && curl -fsSL $R/datos.php -o /tmp/nov_datos.php || { echo "ERROR bajando"; exit 1; }
if ! python3 /tmp/nov_codigo.py "$PWD" || ! php -l app/Services/BotTarifasService.php >/dev/null || ! php -l app/Services/BotPromptService.php >/dev/null; then
  for f in $F; do cp "$f.bak.$TS" "$f"; done; echo "ERROR: restaurado, nada cambió"; exit 1
fi
php /tmp/nov_datos.php "$1"
rm -f /tmp/nov_codigo.py /tmp/nov_datos.php
php artisan config:clear >/dev/null; php artisan view:clear >/dev/null; php artisan queue:restart >/dev/null
sudo mkdir -p public/uploads/bot/afiches && sudo chown -R www-data:www-data public/uploads/bot && echo "Carpeta de afiches lista"
