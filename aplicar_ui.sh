#!/bin/bash
# BotacurApp · menú lateral nuevo (secciones plegables, íconos Material Symbols) + capa visual sobria y responsiva.
# Mantiene el mismo control de roles. Para volver atrás:  bash aplicar_ui.sh revertir
cd /var/www/html/botacurapp || exit 1
M=resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php
H=resources/views/themes/backoffice/layouts/includes/head.blade.php
C=public/assents/backoffice/css/botacura-ui.css

if [ "$1" = "revertir" ]; then
  B=$(ls -t $M.bak.* 2>/dev/null | head -1)
  [ -n "$B" ] && cp "$B" $M && echo "Menú anterior restaurado ($B)"
  sed -i '/botacura-ui.css/d;/Material+Symbols+Outlined/d' $H && echo "Capa visual quitada"
  php artisan view:clear >/dev/null; exit 0
fi

SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)

curl -fsSL "$U/ui/$M" -o /tmp/menu_nuevo.blade.php || { echo "ERROR bajando el menú"; exit 1; }

# Seguridad: ninguna opción del menú actual puede quedar fuera sin aviso
python3 - "$M" /tmp/menu_nuevo.blade.php <<'PY' || { echo "No se instaló: hay opciones del menú actual que el nuevo no tiene (arriba). Avísale a Claude."; [ "$FORZAR" = "1" ] || exit 1; }
import re, sys
viejo = open(sys.argv[1], encoding='utf-8').read()
viejo = re.sub(r'\{\{--.*?--\}\}', '', viejo, flags=re.S)
nuevo = open(sys.argv[2], encoding='utf-8').read()
rv = set(re.findall(r"route\s*\(\s*'([^']+)'", viejo))
rn = set(re.findall(r"'(backoffice\.[a-z0-9_.\-]+)'", nuevo)) | set(re.findall(r"route\s*\(\s*'([^']+)'", nuevo))
# las de finanzas viven ahora en el menú nuevo; egreso.index se reemplaza por Egresos del mes
faltan = sorted(r for r in rv - rn if r not in ('backoffice.egreso.index',))
inc = re.findall(r"@include\('([^']+)'\)", viejo)
inc = [i for i in inc if 'menu_finanzas' not in i]
if faltan or inc:
    print('Opciones que el menú nuevo no tiene:', ', '.join(faltan + ['@include ' + i for i in inc]))
    sys.exit(1)
print('Menú: todas las opciones actuales están en el nuevo')
PY

cp $M $M.bak.$TS && mv /tmp/menu_nuevo.blade.php $M
curl -fsSL "$U/ui/$C" -o $C || { echo "ERROR bajando la hoja de estilos"; cp $M.bak.$TS $M; exit 1; }
for f in resources/views/themes/backoffice/pages/egresos_panel/_estilo.blade.php resources/views/themes/backoffice/pages/egresos_panel/mes.blade.php resources/views/themes/backoffice/pages/finanzas_correos/clasificar.blade.php; do
  [ -f "$f" ] && cp "$f" "$f.bak.$TS" && curl -fsSL "$U/correos/$f" -o "$f"
done
if ! grep -q "botacura-ui.css" $H; then
  cp $H $H.bak.$TS
  python3 - <<'PY'
p='resources/views/themes/backoffice/layouts/includes/head.blade.php'; s=open(p).read()
add = ('<link rel="preconnect" href="https://fonts.googleapis.com">\n'
       '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300,0,0&display=swap" rel="stylesheet">\n'
       '<link href="{{ asset(\'assents/backoffice/css/botacura-ui.css\') }}?v=1" rel="stylesheet">\n')
k = "custom.css') }}\" rel=\"stylesheet\">"
i = s.find(k)
s = s[:i+len(k)] + "\n" + add + s[i+len(k):] if i >= 0 else s + "\n" + add
open(p, 'w').write(s)
PY
fi
php artisan view:clear >/dev/null
echo "----"
echo "Listo. Recarga BotacurApp (Ctrl+Shift+R). Para volver atrás: curl -sL $U/aplicar_ui.sh | bash -s revertir"
