#!/bin/bash
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/reenviar_no_entregados.php -o /tmp/rne.php || exit 1
sudo -u www-data php /tmp/rne.php "${1:-listar}"; rm -f /tmp/rne.php
