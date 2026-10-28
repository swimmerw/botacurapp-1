#!/bin/bash
# El 1 de noviembre de 2026 se cobra con los valores de octubre (bot, cotizador y app).
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}
echo "Version: ${SHA:0:7}"
F=app/Services/BotTarifasService.php; sudo cp $F $F.bak.$(date +%Y%m%d%H%M)
curl -fsSL $R/nov1/patch.py -o /tmp/n1.py && curl -fsSL $R/nov1/datos.php -o /tmp/n1.php || exit 1
sudo python3 /tmp/n1.py "$PWD"; php -l $F >/dev/null || { echo "ERROR: se restaura el respaldo"; sudo cp $(ls -t $F.bak.* | head -1) $F; exit 1; }
sudo -u www-data php /tmp/n1.php 2>&1 | grep -v '^PHP Deprecated'; rm -f /tmp/n1.py /tmp/n1.php
echo LISTO
