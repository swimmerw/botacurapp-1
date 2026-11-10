#!/bin/bash
# Prueba simulada del orden de reserva (no envía mensajes). Opcional: bash -s -- "mensaje 1" "mensaje 2" ...
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/probar_orden.php -o /tmp/po.php || exit 1
sudo -u www-data php /tmp/po.php "$@" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/po.php
