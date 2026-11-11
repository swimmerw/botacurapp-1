#!/bin/bash
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/feriados.php -o /tmp/fe.php || exit 1
sudo -u www-data php /tmp/fe.php "$@" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/fe.php; echo LISTO
