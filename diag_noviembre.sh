#!/bin/bash
# Solo lectura: datos de programas, bot y fechas de noviembre para compararlos con el afiche.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/nov/diag.php" -o /tmp/diag_nov.php && php /tmp/diag_nov.php > /tmp/diag_noviembre.txt 2>&1
rm -f /tmp/diag_nov.php; echo "Guardado en /tmp/diag_noviembre.txt ($(wc -l < /tmp/diag_noviembre.txt) líneas)"; cat /tmp/diag_noviembre.txt
