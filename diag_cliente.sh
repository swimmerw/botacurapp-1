#!/bin/bash
# Solo lectura: reserva, comprobante y conversación de un cliente. Uso: ... | bash -s -- 56932133925
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/diag_diana.php -o /tmp/dc.php || exit 1
sudo -u www-data php /tmp/dc.php "${1:-56932133925}" 2>&1 | grep -v "^PHP Deprecated" > ~/diag_cliente_${1:-56932133925}.txt; rm -f /tmp/dc.php
wc -l ~/diag_cliente_${1:-56932133925}.txt; sudo cp ~/diag_cliente_${1:-56932133925}.txt public/rev-7d1c40e2a9b3.txt && sudo chmod 644 public/rev-7d1c40e2a9b3.txt && echo "LISTO (copia temporal para revisar)"
