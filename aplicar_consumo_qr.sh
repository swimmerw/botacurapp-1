#!/bin/bash
# Gestión consumo: botón "Código QR" (carta digital botacura.ola.click) y, solo administradores,
# botón "Corregir diferencia" por reserva. Incluye la edición de diferencia (aplicar_editar_diferencia.sh),
# así que basta con correr este. Se puede correr de nuevo.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}
echo "Version: ${SHA:0:7}"

echo "== 1/2 Editar diferencia =="
curl -fsSL $R/aplicar_editar_diferencia.sh -o /tmp/aed.sh || exit 1
bash /tmp/aed.sh || exit 1
rm -f /tmp/aed.sh

echo "== 2/2 Gestión consumo: Código QR y Corregir diferencia =="
T=$(date +%Y%m%d%H%M)
V=resources/views/themes/backoffice/pages/venta/cierre/index_cierre.blade.php
sudo cp $V $V.bak.$T
sudo mkdir -p public/images
curl -fsSL $R/consumo_qr/qr_carta.png -o /tmp/qr_carta.png || exit 1
curl -fsSL $R/consumo_qr/patch.py -o /tmp/cqr.py || exit 1
sudo cp /tmp/qr_carta.png public/images/qr_carta.png
sudo chown www-data:www-data public/images/qr_carta.png
sudo python3 /tmp/cqr.py "$PWD" || { echo "ERROR: se restaura el respaldo"; sudo cp $V.bak.$T $V; exit 1; }
sudo -u www-data php artisan view:clear >/dev/null
rm -f /tmp/qr_carta.png /tmp/cqr.py
echo LISTO
