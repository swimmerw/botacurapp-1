#!/bin/bash
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/finanzas7.py -o /tmp/finanzas7.py || exit 1
python3 /tmp/finanzas7.py || exit 1
php artisan view:clear
echo "LISTO"
