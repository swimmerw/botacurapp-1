#!/bin/bash
# Quita la tarjeta "Configuracion del bot" del cuadro Administracion (queda solo en el menu lateral)
cd /var/www/html/botacurapp || exit 1
V=resources/views/themes/backoffice/pages/admin/show.blade.php
cp $V $V.bak.$(date +%Y%m%d%H%M%S)
python3 - "$V" <<'PY'
import re, sys
p = sys.argv[1]; t = open(p, encoding='utf-8').read()
pat = re.compile(r"<div class=\"col s12 m6 l3\">\s*<a href=\"\{\{ route\('backoffice\.bot-configuracion\.index'\) \}\}\">.*?</a>\s*</div>\s*", re.S)
n = len(pat.findall(t))
if n != 1:
    sys.exit('No encontre la tarjeta (coincidencias: %d). No se cambio nada.' % n)
t = pat.sub('', t)
t = re.sub(r"[ \t]*\$botActivo = .*?\n", '', t)
open(p, 'w', encoding='utf-8').write(t)
print('Tarjeta Configuracion del bot quitada')
PY
php artisan view:clear >/dev/null
R=$(php artisan tinker --execute="echo strlen(app('blade.compiler')->compileString(file_get_contents('$V'))) > 0 ? 'Vista OK' : 'Vista vacia';" 2>&1 | tail -1)
echo "$R"
[ "$R" = "Vista OK" ] || { cp "$(ls $V.bak.* | sort | tail -1)" $V; php artisan view:clear >/dev/null; echo "Inicio restaurado"; exit 1; }
echo LISTO
