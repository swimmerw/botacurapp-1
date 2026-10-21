#!/bin/bash
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/proy/servicios.php" -o serv_tmp.php && php serv_tmp.php; rm -f serv_tmp.php
