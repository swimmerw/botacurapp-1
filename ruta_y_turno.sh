#!/bin/bash
# 1) Ruta API de dias pedidos por correo (n8n). 2) Turno real del domingo 18/10: Catalina y Pamela. 17/10/2026
cd /var/www/html/botacurapp || exit 1
R=routes/api.php
if grep -q "InteresFechasCorreoController" $R; then echo "La ruta ya estaba."; else
  cp -p $R $R.bak.$(date +%Y%m%d%H%M%S)
  printf "\n// Consultas por correo de dias no habilitados (n8n). 15/10/2026\nRoute::middleware('cotizador.token')->post('cotizador/interes-fechas', '\\\\App\\\\Http\\\\Controllers\\\\InteresFechasCorreoController@store');\n" >> $R
  php -l $R >/dev/null || { echo "Error en routes/api.php, se restaura"; cp -p $R.bak.* $R; exit 1; }
fi
sg www-data -c "php artisan route:clear >/dev/null; php artisan route:list 2>/dev/null | grep -c 'cotizador/interes-fechas'" | sed 's/^/ruta interes-fechas registrada: /'
sg www-data -c "php artisan tinker --execute='
\$f=\"2026-10-18\"; DB::table(\"turnos_sugeridos\")->where(\"fecha\",\$f)->delete();
foreach ([\"Catalina Maureira\",\"Pamela Tapia\"] as \$n) { DB::table(\"turnos_sugeridos\")->insert([\"fecha\"=>\$f,\"nombre\"=>\$n,\"user_id\"=>App\\User::where(\"name\",\"like\",\$n.\"%\")->value(\"id\"),\"area\"=>\"atencion\",\"created_at\"=>now(),\"updated_at\"=>now()]); }
echo \"Turno del 18/10: \".DB::table(\"turnos_sugeridos\")->where(\"fecha\",\$f)->pluck(\"nombre\")->implode(\", \").PHP_EOL;'"
