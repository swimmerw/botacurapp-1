#!/bin/bash
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/estado_canales.php -o /tmp/ec.php || exit 1
sudo -u www-data php /tmp/ec.php 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/ec.php; echo LISTO
