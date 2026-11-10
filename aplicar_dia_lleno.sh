#!/bin/bash
# 1) El bot informa "sin cupos" (no "no habilitado") en días habilitados con tinajas completas.
# 2) Lista (o envía con "enviar") la corrección a quienes se les ofreció el sábado 21/11.
cd /var/www/html/botacurapp || exit 1
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
BC=app/Http/Controllers/Api/BotController.php
if [ "$1" != "enviar" ]; then
  TS=$(date +%Y%m%d%H%M); cp $BC $BC.bak.$TS
  curl -fsSL $U/bot/patch_dia_lleno_1011.py | python3 - || { cp $BC.bak.$TS $BC; echo "ERROR: restaurado"; exit 1; }
  php -l $BC >/dev/null || { cp $BC.bak.$TS $BC; echo "ERROR de sintaxis: restaurado"; exit 1; }
  php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $c=new App\Http\Controllers\Api\BotController(); $m=new ReflectionMethod($c,"notaFechasDelMensaje"); $m->setAccessible(true); foreach(["este sábado","el sábado 21","el domingo 22 de noviembre"] as $t) echo "  ",$t," ->",str_replace("\n"," ",$m->invoke($c,$t)),"\n";'
fi
echo "== Corrección del sábado 21"
curl -fsSL $U/bot/aviso_sabado21.php -o /tmp/av21.php || exit 1
sudo -u www-data php /tmp/av21.php "${1:-listar}"; rm -f /tmp/av21.php
echo LISTO
