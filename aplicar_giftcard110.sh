#!/bin/bash
# Gift Card = Botacura Full a $110.000 por persona en el bot, el módulo Gift Card y la web.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}
echo "Version: ${SHA:0:7}"
curl -fsSL $R/giftcard110/datos.php -o /tmp/gc110.php || exit 1
sudo -u www-data php /tmp/gc110.php 2>&1 | grep -v '^PHP Deprecated'; rm -f /tmp/gc110.php
echo LISTO
