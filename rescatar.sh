#!/bin/bash
# Uso: ... | bash -s -- listar 09:00      ... | bash -s -- enviar 09:00 1212,697
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/rescatar.php -o /tmp/resc.php || exit 1
sudo -u www-data php /tmp/resc.php "${1:-listar}" "${2:-09:00}" "${3:-}"; rm -f /tmp/resc.php
