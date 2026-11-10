#!/bin/bash
# 10/11/2026 (c): el bot no da datos bancarios sin cupo apartado; fechas invertidas en comprobantes;
# "Crear reserva" desde Comprobantes sin reserva.
cd /var/www/html/botacurapp || exit 1
G=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
curl -fsSL $G/bot/patch_pago_cupo_1011.py -o /tmp/ppc.py || exit 1
OUT=$(sudo python3 /tmp/ppc.py); echo "$OUT"; rm -f /tmp/ppc.py
ST=$(echo "$OUT" | grep PARCHE_OK | awk '{print $2}')
for f in BotController WhatsAppWebhookController; do
  if ! php -l app/Http/Controllers/Api/$f.php >/dev/null; then
    [ -n "$ST" ] && for g in BotController WhatsAppWebhookController; do sudo cp app/Http/Controllers/Api/$g.php.bak.$ST app/Http/Controllers/Api/$g.php; done
    echo "ERROR de sintaxis en el bot: restaurado"; exit 1
  fi
done
V=resources/views/themes/backoffice/pages/comprobantes-sin-reserva
C=app/Http/Controllers/ComprobantesSinReservaController.php
sudo cp $C $C.bak.$(date +%Y%m%d%H%M%S); sudo cp $V/index.blade.php $V/index.blade.php.bak.$(date +%Y%m%d%H%M%S)
sudo curl -fsSL $G/comprobantes/ComprobantesSinReservaController.php -o $C || exit 1
sudo curl -fsSL $G/comprobantes/index.blade.php -o $V/index.blade.php || exit 1
curl -fsSL $G/comprobantes/instalar.py -o /tmp/ci.py && sudo python3 /tmp/ci.py; rm -f /tmp/ci.py
php -l $C >/dev/null && php -l routes/web.php >/dev/null || { echo "ERROR de sintaxis en la pantalla"; exit 1; }
sudo -u www-data php artisan view:clear >/dev/null; sudo -u www-data php artisan route:clear >/dev/null; php artisan config:clear >/dev/null
php artisan route:list 2>/dev/null | grep -c "comprobantes-sin-reserva" | sed 's/^/Rutas de la pantalla: /'
echo "LISTO"
