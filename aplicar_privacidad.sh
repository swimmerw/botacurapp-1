#!/bin/bash
# Página pública de política de privacidad: https://app.botacura.cl/privacidad (la pide Meta para publicar la app)
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
F=resources/views/privacidad.blade.php
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/privacidad/$F" -o "$F" || { echo "ERROR bajando la vista"; exit 1; }
W=routes/web.php
if ! grep -q "'privacidad'" $W; then
  cp $W $W.bak.$TS
  printf "\n// Política de privacidad pública (Meta)\nRoute::view('privacidad', 'privacidad')->name('privacidad');\nRoute::redirect('privacy-policy', '/privacidad');\n" >> $W
  php -l $W >/dev/null || { cp $W.bak.$TS $W; echo "ERROR de sintaxis, restaurado"; exit 1; }
fi
php artisan route:clear >/dev/null 2>&1; php artisan view:clear >/dev/null 2>&1
CODE=$(curl -s -o /dev/null -w "%{http_code}" https://app.botacura.cl/privacidad)
echo "https://app.botacura.cl/privacidad -> HTTP $CODE"
[ "$CODE" = "200" ] && echo "LISTO" || echo "REVISAR: la página no respondió 200"
