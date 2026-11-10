#!/bin/bash
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/revisar_conversaciones.php -o /tmp/rc.php || exit 1
sudo -u www-data php /tmp/rc.php "${1:-12}"; rm -f /tmp/rc.php
