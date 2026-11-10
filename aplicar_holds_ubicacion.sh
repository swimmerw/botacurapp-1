#!/bin/bash
# (a) texto de ubicación según lo asignado; (b) cupos apartados cuentan; (c) #2808 a Estación 4; (d) aviso a Camila.
# Sin argumento: aplica (a), (b), (c) y muestra el mensaje a Camila. Con "enviar": solo envía el mensaje (d).
cd /var/www/html/botacurapp || exit 1
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
if [ "$1" != "enviar" ]; then
  TS=$(date +%Y%m%d%H%M)
  BU=app/Services/BotUbicacionesService.php; DS=app/Services/DisponibilidadService.php
  cp $BU $BU.bak.$TS; cp $DS $DS.bak.$TS
  R() { cp $BU.bak.$TS $BU; cp $DS.bak.$TS $DS; echo "ERROR: $1. Restaurado."; exit 1; }
  curl -fsSL $U/bot/patch_holds_ubicacion_1011.py | python3 - || R "el parche no calzó"
  php -l $BU >/dev/null || R "sintaxis BotUbicacionesService"; php -l $DS >/dev/null || R "sintaxis DisponibilidadService"
  php artisan cache:clear >/dev/null
  echo "Código OK. Comprobación 28/11 (con el cupo de Camila contando):"
  php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $s=app(App\Services\DisponibilidadService::class); echo "  slotsTinaja: ",json_encode($s->slotsTinaja("2026-11-28",0)),"\n  capacidadLibre: ",json_encode($s->capacidadLibre("2026-11-28")),"\n"; $pid=DB::table("programas")->where("nombre_programa","Relax Day")->value("id"); $p=App\Services\BotUbicacionesService::plan("2026-11-28",(int)$pid,5,null); echo "  Otro grupo de 5 en Relax Day hoy recibiría: ",json_encode($p["asignacion"])," · \"",$p["texto_cliente"],"\"\n";'
  echo "Respaldo: $BU.bak.$TS y $DS.bak.$TS"
fi
curl -fsSL $U/bot/ajustes_28nov.php -o /tmp/aj28.php || exit 1
sudo -u www-data php /tmp/aj28.php "${1:-mostrar}" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/aj28.php
echo LISTO
