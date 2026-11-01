#!/bin/bash
# Bot-Acura: reglas separadas por canal (Solo WhatsApp / Solo Instagram) y almuerzo de 3 tiempos,
# editables en Configuración del bot › Reglas comerciales. Se puede correr de nuevo.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/bot
echo "Version: ${SHA:0:7}"
T=$(date +%Y%m%d%H%M)
FILES="app/Services/BotPromptService.php app/Http/Controllers/Api/BotController.php"
for f in $FILES; do sudo cp $f $f.bak.$T; done
restaurar(){ for f in $FILES; do sudo cp $f.bak.$T $f; done; echo "ERROR: $1 (se restauraron los respaldos)"; exit 1; }
curl -fsSL $R/patch_canales.py -o /tmp/bcp.py || exit 1
curl -fsSL $R/sembrar_canales.php -o sembrar_canales_tmp_$T.php || exit 1
sudo python3 /tmp/bcp.py "$PWD" || restaurar "parche"
for f in $FILES; do php -l $f >/dev/null || restaurar "sintaxis en $f"; done
sudo -u www-data php artisan config:clear >/dev/null; sudo -u www-data php artisan cache:clear >/dev/null
php sembrar_canales_tmp_$T.php 2>&1 | grep -v Deprecated
rm -f /tmp/bcp.py sembrar_canales_tmp_$T.php
echo "Respaldos: *.bak.$T"
echo LISTO
