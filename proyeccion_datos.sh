#!/bin/bash
# Solo lectura: junta ingresos, egresos, programas, carta, servicios y equipo para la proyección.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/proy/datos.php" -o proy_tmp.php && php -d memory_limit=1G proy_tmp.php; rm -f proy_tmp.php
