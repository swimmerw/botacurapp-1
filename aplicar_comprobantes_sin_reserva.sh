#!/bin/bash
# Pantalla "Comprobantes sin reserva" (10/11/2026) + aviso en Verificacion de pagos y Reservas del dia.
cd /var/www/html/botacurapp || exit 1
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/comprobantes
if ! grep -q esComprobanteSinReserva app/Http/Controllers/Api/WhatsAppWebhookController.php; then
  echo "== Primero el parche del bot del 09/11"
  curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/aplicar_bot_pagos.sh | bash || exit 1
fi
V=resources/views/themes/backoffice/pages/comprobantes-sin-reserva
sudo mkdir -p $V
sudo curl -fsSL $R/ComprobanteTextoService.php -o app/Services/ComprobanteTextoService.php || exit 1
sudo curl -fsSL $R/ComprobantesSinReservaController.php -o app/Http/Controllers/ComprobantesSinReservaController.php || exit 1
sudo curl -fsSL $R/index.blade.php -o $V/index.blade.php || exit 1
sudo curl -fsSL $R/_aviso.blade.php -o $V/_aviso.blade.php || exit 1
curl -fsSL $R/instalar.py -o /tmp/csr_instalar.py && sudo python3 /tmp/csr_instalar.py || exit 1
for f in app/Services/ComprobanteTextoService.php app/Http/Controllers/ComprobantesSinReservaController.php app/Http/Controllers/Api/WhatsAppWebhookController.php routes/web.php; do
  php -l $f >/dev/null || { echo "ERROR de sintaxis en $f"; exit 1; }
done
curl -fsSL $R/backfill.php -o /tmp/csr_backfill.php && sudo -u www-data php /tmp/csr_backfill.php; rm -f /tmp/csr_backfill.php /tmp/csr_instalar.py
sudo -u www-data php artisan view:clear >/dev/null; sudo -u www-data php artisan route:clear >/dev/null
php artisan route:list 2>/dev/null | grep "comprobantes-sin-reserva.index" | awk "{for(i=1;i<=NF;i++) if(\$i ~ /comprobantes-sin-reserva\$/) print \"Ruta OK: /\" \$i}"
echo "LISTO"
