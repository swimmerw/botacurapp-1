#!/bin/bash
# Solo lectura. Uso: ... | bash -s -- 56990908493 2026-11-14 2
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/diag_cupo.php -o /tmp/diag_cupo.php || exit 1
sudo -u www-data php /tmp/diag_cupo.php "$1" "$2" "${3:-2}"; rm -f /tmp/diag_cupo.php
