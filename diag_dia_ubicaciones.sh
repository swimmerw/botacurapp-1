#!/bin/bash
# Solo lectura: ubicaciones ocupadas y cupos apartados de un día. Uso: ... | bash -s -- 2026-11-28
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/diag_dia_ubicaciones.php -o /tmp/ddu.php || exit 1
sudo -u www-data php /tmp/ddu.php "${1:-2026-11-28}" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/ddu.php
