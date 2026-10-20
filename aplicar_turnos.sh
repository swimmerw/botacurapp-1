#!/bin/bash
# Equipo › Turnos sugeridos: propone el equipo de cada día según los clientes reservados y avisa por WhatsApp
# el día anterior a las 15:00. Este instalador NO envía nada: deja todo listo y muestra la propuesta de la semana.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/equipo
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
FILES="database/migrations/2026_10_21_100000_create_equipo_turnos.php app/Equipo/Turnos.php app/Console/Commands/EquipoTurnosManana.php app/Http/Controllers/EquipoTurnosController.php routes/equipo.php resources/views/themes/backoffice/pages/equipo_turnos/index.blade.php"
for f in $FILES; do
  mkdir -p "$(dirname $f)"
  curl -fsSL "$U/$f" -o "$f.nuevo" || { echo "ERROR bajando $f"; exit 1; }
  case $f in *.blade.php) ;; *) php -l "$f.nuevo" >/dev/null || { echo "ERROR de sintaxis en $f"; rm -f "$f.nuevo"; exit 1; } ;; esac
  [ -f "$f" ] && cp "$f" "$f.bak.$TS"
  mv "$f.nuevo" "$f"
done
php artisan migrate --force --path=database/migrations/2026_10_21_100000_create_equipo_turnos.php | tail -1

W=routes/web.php
if ! grep -q "routes/equipo.php" $W; then
  cp $W $W.bak.$TS
  python3 - <<'PY' || { cp routes/web.php.bak.$TS routes/web.php; echo "AVISO: rutas sin enlazar"; }
p='routes/web.php'; s=open(p).read()
k="require base_path('routes/finanzas.php');"
i=s.find(k)
if i<0: raise SystemExit(1)
j=s.find('\n', i)
s=s[:j]+"\n    require base_path('routes/equipo.php'); // Equipo: turnos sugeridos"+s[j:]
open(p,'w').write(s)
PY
fi
SB=resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php
if [ -f $SB ] && ! grep -q "backoffice.equipo.turnos" $SB; then
  cp $SB $SB.bak.$TS
  python3 - <<'PY' || echo "AVISO: menú sin el enlace (entra por /equipo/turnos)"
p='resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php'; s=open(p).read()
k="'backoffice.asignacion.index'"
i=s.find(k)
if i<0: raise SystemExit(1)
j=s.find('\n', i)
s=s[:j]+"\n            ['Turnos sugeridos', 'groups', 'backoffice.equipo.turnos', [], $rol('admin', 'jefe_local'), 'backoffice.equipo.*'],"+s[j:]
open(p,'w').write(s)
PY
fi
K=app/Console/Kernel.php
if ! grep -q "equipo:turnos-manana" $K; then
  cp $K $K.bak.$TS
  python3 - <<'PY' || { cp app/Console/Kernel.php.bak.$TS app/Console/Kernel.php; echo "ERROR en Kernel"; }
import re
p='app/Console/Kernel.php'; s=open(p).read()
m=re.search(r'protected function schedule\(Schedule \$schedule\)\s*\{\n', s)
if not m: raise SystemExit(1)
s=s[:m.end()]+"        // Turnos sugeridos del día siguiente: aviso por WhatsApp a las 15:00\n        $schedule->command('equipo:turnos-manana --enviar')->dailyAt('15:00')->timezone('America/Santiago');\n"+s[m.end():]
open(p,'w').write(s)
PY
  php -l $K >/dev/null || { cp $K.bak.$TS $K; echo "ERROR de sintaxis en Kernel, restaurado"; }
fi

# Plantilla de WhatsApp para el aviso (la de las alertas de Home Assistant, si tiene un solo parámetro)
if ! grep -q '^EQUIPO_PLANTILLA=' .env; then
  php -r '
require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$waba = env("HA_WABA_ID", "27846116655070945"); $v = env("META_API_VERSION", "v19.0");
$r = (new GuzzleHttp\Client(["http_errors"=>false,"timeout"=>15]))->get("https://graph.facebook.com/$v/$waba/message_templates", ["query"=>["name"=>"alerta_botacura","fields"=>"name,language,status,components","access_token"=>env("META_WHATSAPP_TOKEN")]]);
$d = json_decode((string)$r->getBody(), true);
foreach (($d["data"] ?? []) as $t) {
  $body = collect($t["components"])->firstWhere("type", "BODY")["text"] ?? "";
  preg_match_all("/\{\{\d+\}\}/", $body, $m);
  echo "Plantilla {$t["name"]} ({$t["language"]}, {$t["status"]}): \"$body\"\n";
  if ($t["status"] === "APPROVED" && count($m[0]) === 1) { file_put_contents(".env", "\n# Aviso de turnos (plantilla de WhatsApp con un parámetro)\nEQUIPO_PLANTILLA=alerta_botacura\nEQUIPO_PLANTILLA_IDIOMA={$t["language"]}\n", FILE_APPEND); echo "  → se usará para el aviso de turnos\n"; exit; }
}
echo isset($d["error"]) ? "No pude leer las plantillas: ".$d["error"]["message"]."\n" : "Sin plantilla con un parámetro: el aviso irá como texto (llega si escribiste a ese número en las últimas 24 h)\n";'
fi
php artisan config:clear >/dev/null; php artisan route:clear >/dev/null; php artisan view:clear >/dev/null
echo "---- Equipo (configuración inicial; se ajusta en Equipo › Turnos sugeridos)"
php artisan equipo:turnos-manana --sembrar --dias=0 2>/dev/null | grep -v "cerrado y sin"
echo "---- Propuesta de los próximos 7 días (no se envía nada)"
php artisan equipo:turnos-manana --dias=7
echo "Listo: app.botacura.cl/equipo/turnos · aviso automático todos los días a las 15:00"
echo "Probar el aviso ahora: php artisan equipo:turnos-manana --enviar --forzar"
