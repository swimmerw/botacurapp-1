#!/bin/bash
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/giftcard_textos_1111.php -o /tmp/gt.php || exit 1
php /tmp/gt.php 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/gt.php; echo LISTO
