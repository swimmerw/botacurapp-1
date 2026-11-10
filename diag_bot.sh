#!/bin/bash
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/diag_bot.php -o /tmp/diag_bot.php || exit 1
sudo -u www-data php /tmp/diag_bot.php 2>&1 | sed -E 's/(access_token|x-api-key)=[^& ]*/\1=***/g'; rm -f /tmp/diag_bot.php
