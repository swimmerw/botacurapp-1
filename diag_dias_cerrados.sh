#!/bin/bash
# Solo lectura: por qué el sistema ve cupo en días cerrados. Uso: ... | bash   (o bash -s -- 2026-11-14 2026-11-21)
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/diag_dias_cerrados.php -o /tmp/ddc.php || exit 1
sudo -u www-data php /tmp/ddc.php "$@" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/ddc.php
