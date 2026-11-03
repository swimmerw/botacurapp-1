#!/bin/bash
# botacura.cl: quita de las fichas de programas el aviso "Desde noviembre atendemos de lunes a domingo…"
# y vuelve a publicar solo las fichas que lo tienen.
# Uso: curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/aplicar_quitar_aviso_web.sh | bash
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/web_aviso
echo "Version: ${SHA:0:7}"
F=app/Services/ProgramaContentBuilder.php; B=$F.bak.$(date +%Y%m%d%H%M); sudo cp $F $B
curl -fsSL $R/patch.py -o /tmp/wa_patch.py && curl -fsSL $R/resync.php -o /tmp/wa_resync.php || exit 1
sudo python3 /tmp/wa_patch.py "$PWD" && php -l $F >/dev/null || { sudo cp $B $F; echo "ERROR (se restauró el respaldo)"; exit 1; }
grep -q "Desde noviembre atendemos" $F && { sudo cp $B $F; echo "ERROR: el aviso sigue en el builder"; exit 1; }
echo "---- Fichas en la web"
php /tmp/wa_resync.php 2>&1 | grep -v Deprecated
rm -f /tmp/wa_patch.py /tmp/wa_resync.php
echo LISTO
