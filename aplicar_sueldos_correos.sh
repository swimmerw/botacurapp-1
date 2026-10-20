#!/bin/bash
# 1) El aviso del banco confirma el sueldo en Remuneraciones (también los que ya estaban registrados sin confirmar).
# 2) Reinicia la cola de correos para que use el código nuevo y manda dos correos de prueba.
# Uso: curl -sL .../aplicar_sueldos_correos.sh | bash -s tu@correo.cl
cd /var/www/html/botacurapp || exit 1
DEST="$1"
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/sueldos
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
FILES="app/Finanzas/SueldoSemanas.php app/Finanzas/Correo/ConciliadorSalidas.php app/Http/Controllers/FinanzasCorreoController.php"
for f in $FILES; do cp $f $f.bak.$TS; done
restaurar() { for f in $FILES; do cp $f.bak.$TS $f; done; echo "ERROR: $1 (restaurado)"; exit 1; }
curl -fsSL "$U/patch_confirmar.py" | python3 - || restaurar "parche"
for f in $FILES; do php -l $f >/dev/null || restaurar "sintaxis en $f"; done
curl -fsSL "$U/app/Mail/PruebaMailable.php" -o app/Mail/PruebaMailable.php && php -l app/Mail/PruebaMailable.php >/dev/null || echo "Aviso: no pude instalar el correo de prueba"
mkdir -p /tmp/bt_$TS && curl -fsSL "$U/retro.php" -o retro_tmp_$TS.php && curl -fsSL "$U/prueba_correo.php" -o prueba_tmp_$TS.php
echo "---- Remuneraciones con aviso del banco"
php retro_tmp_$TS.php
echo "---- Cola de correos"
for P in $(pgrep -f "artisan queue:work"); do
  echo "  proceso $P · lo maneja: $(ps -o unit= -p $P 2>/dev/null) · padre: $(ps -o cmd= -p $(ps -o ppid= -p $P) | cut -c1-60)"
done
UNIT=$(ps -o unit= -p $(pgrep -f "artisan queue:work" | head -1) 2>/dev/null | tr -d ' ')
PADRE=$(ps -o cmd= -p $(ps -o ppid= -p $(pgrep -f "artisan queue:work" | head -1)) 2>/dev/null)
if echo "$PADRE" | grep -q supervisord || { [ -n "$UNIT" ] && echo "$UNIT" | grep -q '\.service$' && ! echo "$UNIT" | grep -q '^session'; }; then
  php artisan queue:restart && echo "  Cola reiniciada (se levanta sola con el código nuevo)"
else
  echo "  No reinicio la cola: no hay quién la vuelva a levantar sola. Dime y lo dejamos como servicio."
fi
if [ -n "$DEST" ]; then
  sleep 5
  echo "---- Prueba de envío a $DEST"
  php prueba_tmp_$TS.php "$DEST"
  sleep 12
  php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo "  En cola ahora: ", DB::table("jobs")->count(), " · fallidos en los últimos 10 min: ", DB::table("failed_jobs")->where("failed_at", ">=", now()->subMinutes(10))->count(), "\n";'
fi
rm -f retro_tmp_$TS.php prueba_tmp_$TS.php; rmdir /tmp/bt_$TS 2>/dev/null
echo "Si algo sale mal: for f in $FILES; do cp \$f.bak.$TS \$f; done"
