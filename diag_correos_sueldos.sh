#!/bin/bash
# Diagnóstico (solo lectura): por qué no salen los correos automáticos y por qué no se marcan las remuneraciones.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/bot/diag.php" -o /tmp/botacura_diag.php || { echo "ERROR bajando"; exit 1; }
cp /tmp/botacura_diag.php ./botacura_diag_tmp.php && php botacura_diag_tmp.php; rm -f botacura_diag_tmp.php /tmp/botacura_diag.php
