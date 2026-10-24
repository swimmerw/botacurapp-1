#!/bin/bash
# Ventas de botacura.cl → app: toma los pagos Webpay ("processing") además de los "completed", marca canceladas
# las reservas de pedidos cancelados o reembolsados, y pausa el webhook viejo de desarrollo (ngrok).
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/wcpago
F=app/Http/Controllers/Api/WoocommerceWebhookController.php; cp $F $F.bak.$(date +%Y%m%d%H%M)
curl -fsSL $R/patch.py -o /tmp/wc1.py && curl -fsSL $R/pausar_dev.php -o /tmp/wc2.php || exit 1
python3 /tmp/wc1.py "$PWD" && php -l $F >/dev/null || { cp $F.bak.* $F 2>/dev/null; echo ERROR; exit 1; }
php /tmp/wc2.php; rm -f /tmp/wc1.py /tmp/wc2.php
php artisan route:clear >/dev/null; php artisan queue:restart >/dev/null; echo LISTO
