#!/bin/bash
# Carga en Por pagar los F29 pendientes: enero, junio, agosto y septiembre 2026.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
C=app/Console/Commands/FinanzasF29Deudas.php
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/f29/$C" -o $C.nuevo && php -l $C.nuevo >/dev/null && mv $C.nuevo $C || { rm -f $C.nuevo; echo "ERROR"; exit 1; }
php artisan finanzas:f29-deudas --solo=2026-01,2026-06,2026-08,2026-09 --aplicar
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $r=app(App\Finanzas\Egresos\Deudas::class)->resumen(["botacura","personal"]); echo "Deuda pendiente total: $", number_format($r["total"],0,",","."), "\n"; foreach($r["por_tipo"] as $t=>$x) echo "  $t: $", number_format($x["total"],0,",","."), "\n";'
