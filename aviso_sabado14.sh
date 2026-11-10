#!/bin/bash
# Aclaración del sábado 14/11 (cerrado, sin cupos). Sin argumento: solo lista. Con "enviar": envía.
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/aviso_sabado14.php -o /tmp/av14.php || exit 1
sudo -u www-data php /tmp/av14.php "${1:-listar}"; rm -f /tmp/av14.php
