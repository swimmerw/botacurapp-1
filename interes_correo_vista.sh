#!/bin/bash
# BotacurApp (AWS): en "Dias pedidos sin habilitar", las personas que llegaron por correo
# tienen boton "Correo" (mailto con el aviso) en vez de WhatsApp. 15/10/2026
set -e
cd /var/www/html/botacurapp
V=resources/views/themes/backoffice/pages/bot-conversaciones/index.blade.php
S=app/Services/BotInteresFechasService.php
T=$(date +%Y%m%d%H%M%S)
cp -p $V $V.bak.$T; cp -p $S $S.bak.$T
python3 - "$V" "$S" <<'PY'
import re, sys
v, s = sys.argv[1], sys.argv[2]
t = open(v, encoding='utf-8').read()
if 'mailto:' not in t:
    pat = r'href="https://wa\.me/\{\{ \$p->telefono \}\}\?text=\{\{ rawurlencode\(\\App\\Services\\BotInteresFechasService::textoAviso\(\$p->nombre, \$d->fecha\)\) \}\}">WhatsApp</a>'
    if len(re.findall(pat, t)) != 1: sys.exit('Vista: el enlace no calza')
    nuevo = ('href="{{ (!empty($p->correo) && strpos($p->telefono, \'m\') === 0) '
             '? \'mailto:\' . $p->correo . \'?subject=\' . rawurlencode(\'Botacura: habilitamos el día que consultaste\') . \'&body=\' . rawurlencode(\\App\\Services\\BotInteresFechasService::textoAviso($p->nombre, $d->fecha)) '
             ': \'https://wa.me/\' . $p->telefono . \'?text=\' . rawurlencode(\\App\\Services\\BotInteresFechasService::textoAviso($p->nombre, $d->fecha)) }}">'
             '{{ (!empty($p->correo) && strpos($p->telefono, \'m\') === 0) ? \'Correo\' : \'WhatsApp\' }}</a>')
    t = re.sub(pat, lambda m: nuevo, t)
    open(v, 'w', encoding='utf-8').write(t)
print('Vista OK')
c = open(s, encoding='utf-8').read()
if "str_replace('✉'" not in c:
    pat = r"(public static function textoAviso\(\?string \$nombre, string \$fecha\): string\s*\{)"
    if len(re.findall(pat, c)) != 1: sys.exit('textoAviso no calza')
    c = re.sub(pat, lambda m: m.group(1) + "\n        $nombre = trim(str_replace('✉', '', (string) $nombre)) ?: null;", c)
    open(s, 'w', encoding='utf-8').write(c)
print('Servicio OK')
PY
php -l $S >/dev/null || { cp -p $S.bak.$T $S; cp -p $V.bak.$T $V; echo "Error, restaurado"; exit 1; }
php artisan view:clear >/dev/null
echo "LISTO: boton Correo para las consultas que llegaron por correo."
