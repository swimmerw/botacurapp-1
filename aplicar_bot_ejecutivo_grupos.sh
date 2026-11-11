#!/bin/bash
# Bot-Acura (11/11/2026): palabra EJECUTIVO, grupos de 9 a 24 reservan directo, bebestibles aparte (carta), tuteo chileno.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4); [ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA; echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
BC=app/Http/Controllers/Api/BotController.php; BP=app/Services/BotPromptService.php; BT=app/Services/BotTarifasService.php; WH=app/Http/Controllers/Api/WhatsAppWebhookController.php
for f in $BC $BP $BT $WH; do cp $f $f.bak.$TS; done
R() { for f in $BC $BP $BT $WH; do cp $f.bak.$TS $f; done; echo "ERROR: $1. Restaurado, no cambió nada."; exit 1; }
curl -fsSL $U/bot/patch_ejecutivo_1111.py -o /tmp/pe.py || R "no se pudo descargar"
python3 /tmp/pe.py || R "el parche no calzó"; rm -f /tmp/pe.py
curl -fsSL $U/bot/patch_comprobante_destino_1111.py | python3 - || echo "(el arreglo de comprobantes no se aplicó; lo demás sigue)"
for f in $BC $BP $BT $WH; do php -l $f >/dev/null || R "sintaxis en $f"; done
echo "Código OK"
curl -fsSL $U/bot/textos_ejecutivo_1111.php -o /tmp/te.php && php /tmp/te.php 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/te.php
php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null
echo "Prueba (Full Day): lunes 16/11, miércoles 18/11 y hoy, para 12, 9 y 2 personas:"
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $pid=DB::table("programas")->where("nombre_programa","Full Day")->value("id"); foreach(["2026-11-16","2026-11-18",date("Y-m-d")] as $f){ echo "  $f (personas ya en el día: ",App\Services\BotTarifasService::personasDelDia($f),"): "; foreach([12,9,2] as $p){ $c=App\Services\BotTarifasService::cotizar(["programa_id"=>$pid,"fecha"=>$f,"personas"=>$p,"tipo_pago"=>"Transferencia"]); echo "{$p}→",$c["ok"]?"SÍ":"no(".implode(",",array_column($c["errores"],"codigo")).")","  "; } echo "\n"; }'
echo "Si algo sale mal: for f in $BC $BP $BT $WH; do cp \$f.bak.$TS \$f; done"
echo LISTO
