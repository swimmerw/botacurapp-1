#!/bin/bash
# Fichas de programas en botacura.cl: "lunes a domingo" en vez de "Jueves a Domingo", piscina en Relax Day y Full Day, re-sincroniza.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/webprod
F=app/Services/ProgramaContentBuilder.php; cp $F $F.bak.$(date +%Y%m%d%H%M)
curl -fsSL $R/patch.py -o /tmp/wp_patch.py && curl -fsSL $R/datos.php -o /tmp/wp_datos.php || exit 1
python3 /tmp/wp_patch.py "$PWD" && php -l $F >/dev/null || { echo ERROR; exit 1; }
php /tmp/wp_datos.php; rm -f /tmp/wp_patch.py /tmp/wp_datos.php; echo LISTO
