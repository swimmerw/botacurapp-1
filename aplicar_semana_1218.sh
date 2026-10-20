#!/bin/bash
# Lee los avisos de BancoEstado de hoy (los "Código de Autorización" ya no cuentan como abono) y marca la semana 12-18/10
# de quienes se pagaron en la mañana sin aviso. Sin --aplicar solo muestra. Uso: ... | bash   o   ... | bash -s -- --aplicar
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/sueldos2
TS=$(date +%Y%m%d%H%M)
L=app/Finanzas/Correo/Lector.php
cp $L $L.bak.$TS
curl -fsSL "$U/patch_codigo.py" | python3 - || { cp $L.bak.$TS $L; echo ERROR; exit 1; }
php -l $L >/dev/null || { cp $L.bak.$TS $L; echo "ERROR de sintaxis, restaurado"; exit 1; }
curl -fsSL "$U/semana.php" -o semana_tmp_$TS.php && php semana_tmp_$TS.php "$@"; rm -f semana_tmp_$TS.php
echo "---- Avisos de hoy (se guardan y se cruzan con Remuneraciones)"
php artisan finanzas:leer-correos --dias=1 | tail -n 4
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach (DB::table("fin_correos")->where("tipo","pago")->where("fecha", date("Y-m-d"))->orderBy("id")->get() as $c) echo "  ", $c->nombre, " $", number_format($c->monto,0,",","."), " · ", $c->estado, "/", $c->vinculo ?: "-", " · ", $c->nota, "\n";'
