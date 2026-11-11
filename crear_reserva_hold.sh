#!/bin/bash
# Crea la reserva de un cupo apartado vencido. Uso: ... | bash -s -- 24   (muestra)   |   bash -s -- 24 crear once
cd /var/www/html/botacurapp || exit 1
rm -f public/rev-7d1c40e2a9b3.txt   # copia temporal del diagnóstico
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/crear_reserva_hold.php -o /tmp/crh.php || exit 1
sudo -u www-data php /tmp/crh.php "$@" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/crh.php
