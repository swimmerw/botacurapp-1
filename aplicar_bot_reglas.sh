#!/bin/bash
# Bot-Acura: fechas con temporada y programas S ya calculados (corrige "30 de octubre = verano") y regla de niños en programas de 2.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
echo "Version: ${SHA:0:7}"
P=app/Services/BotPromptService.php
TS=$(date +%Y%m%d%H%M)
cp $P $P.bak.$TS
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/bot/patch_prompt.py" | python3 - || { cp $P.bak.$TS $P; echo "ERROR, restaurado"; exit 1; }
php -l $P >/dev/null || { cp $P.bak.$TS $P; echo "ERROR de sintaxis, restaurado"; exit 1; }
php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null
echo "Así ve el bot las próximas fechas:"
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach(["2026-10-29","2026-10-30","2026-10-31","2026-11-05"] as $f) echo "  ",App\Services\BotPromptService::etiquetaFecha($f),"\n"; $p=App\Services\BotTarifasService::parametros(); echo "  Meses programas S: ",$p["param_meses_semana"]," · Meses verano: ",$p["param_meses_verano"],"\n";'
echo "Si algo sale mal: cp $P.bak.$TS $P"
