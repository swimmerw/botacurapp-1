#!/bin/bash
# Remuneraciones: botón "Sincronizar boletas SII" (solo administradores).
# Trae del SII las boletas de honorarios de las semanas del mes que se está viendo
# (incluye el mes vecino si la semana cruza de mes) y actualiza la columna Boleta.
# Uso: curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/aplicar_sync_bhe.sh | bash
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/sync_bhe
echo "Version: ${SHA:0:7}"
T=$(date +%Y%m%d%H%M)
FILES="routes/web.php app/Http/Controllers/SueldoController.php resources/views/themes/backoffice/pages/sueldo/index.blade.php"
for f in $FILES; do sudo cp $f $f.bak.$T; done
restaurar(){ for f in $FILES; do sudo cp $f.bak.$T $f; done; echo "ERROR: $1 (se restauraron los respaldos)"; exit 1; }
curl -fsSL $R/patch.py -o /tmp/syncbhe.py || exit 1
sudo python3 /tmp/syncbhe.py "$PWD" || restaurar "parche"
for f in routes/web.php app/Http/Controllers/SueldoController.php; do php -l $f >/dev/null || restaurar "sintaxis en $f"; done
sudo -u www-data php artisan route:clear >/dev/null; sudo -u www-data php artisan view:clear >/dev/null
sudo -u www-data php artisan view:clear >/dev/null
sudo -u www-data php artisan route:list 2>/dev/null | grep -q "sincronizar-honorarios" && echo "Ruta OK" || restaurar "no quedó la ruta"
rm -f /tmp/syncbhe.py
echo LISTO
