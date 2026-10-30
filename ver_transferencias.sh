#!/bin/bash
# Lista los usuarios activos habilitados para el CSV de transferencias, con sus correos. Solo lee, no cambia nada.
cd /var/www/html/botacurapp || exit 1
T=$(date +%Y%m%d%H%M%S)
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/sueldos_csv/lista_transferencias.php -o lista_tmp_$T.php || exit 1
php lista_tmp_$T.php 2>&1 | grep -v Deprecated
rm -f lista_tmp_$T.php
