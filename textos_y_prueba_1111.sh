#!/bin/bash
# Termina los textos del bot (lunes a miércoles) y corre la prueba de grupos. No toca código.
cd /var/www/html/botacurapp || exit 1
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
curl -fsSL $U/bot/textos_ejecutivo_1111.php -o /tmp/te.php && php /tmp/te.php 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/te.php
php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null
echo "Prueba (Full Day): lunes 16/11, miércoles 18/11 y hoy, para 12, 9 y 2 personas:"
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $pid=DB::table("programas")->where("nombre_programa","Full Day")->value("id"); foreach(["2026-11-16","2026-11-18",date("Y-m-d")] as $f){ echo "  $f (personas ya en el día: ",App\Services\BotTarifasService::personasDelDia($f),"): "; foreach([12,9,2] as $p){ $c=App\Services\BotTarifasService::cotizar(["programa_id"=>$pid,"fecha"=>$f,"personas"=>$p,"tipo_pago"=>"Transferencia"]); echo "{$p}→",$c["ok"]?"SÍ":"no(".implode(",",array_column($c["errores"],"codigo")).")","  "; } echo "\n"; }'
echo LISTO
