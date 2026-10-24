#!/bin/bash
# Fichas de botacura.cl: descripción corta según afiche de noviembre (Botacura Full / Gift Card $110.000) y regla lunes a miércoles.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}
echo "Version: ${SHA:0:7}"
F=app/Services/ProgramaContentBuilder.php; sudo cp $F $F.bak.$(date +%Y%m%d%H%M)
curl -fsSL $R/fichas_web/patch.py -o /tmp/fw.py && curl -fsSL $R/fichas_web/datos.php -o /tmp/fw.php || exit 1
sudo python3 /tmp/fw.py "$PWD"; php -l $F >/dev/null || { echo "ERROR en la ficha"; exit 1; }
sudo -u www-data php /tmp/fw.php 2>&1 | grep -v '^PHP Deprecated' ; rm -f /tmp/fw.py /tmp/fw.php
echo LISTO
