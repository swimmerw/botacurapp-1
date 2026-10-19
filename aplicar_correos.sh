#!/bin/bash
# Finanzas fase 1 (19/10/2026): lectura automatica de correos bancarios.
#  - Abonos recibidos (avisos de BancoEstado, Banco de Chile, Santander, Falabella, Scotiabank, Bci...)
#    y su cruce con reservas, abonos extra y comprobantes del bot en revision.
#  - Pagos hechos por Botacura, cuentas por pagar (Movistar, API Gateway...), cartolas y facturas.
#  - Vista Finanzas > Correos bancarios (solo FINANZAS_USUARIOS) y lectura cada 5 minutos.
# No cambia estados de ventas ni reservas: solo lee, guarda y marca.
cd /var/www/html/botacurapp || exit 1
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/correos
F="config/finanzas.php app/Finanzas/Correo/ImapCliente.php app/Finanzas/Correo/Mime.php app/Finanzas/Correo/Lector.php app/Finanzas/Correo/ConciliadorAbonos.php app/Console/Commands/LeerCorreosBancarios.php app/Http/Controllers/FinanzasCorreoController.php routes/finanzas.php database/migrations/2026_10_19_200000_create_fin_correos_table.php resources/views/themes/backoffice/pages/finanzas_correos/index.blade.php resources/views/themes/backoffice/pages/finanzas_correos/show.blade.php resources/views/themes/backoffice/layouts/includes/menu_finanzas.blade.php"
TS=$(date +%Y%m%d%H%M)
for f in $F; do
  mkdir -p "$(dirname "$f")"
  [ -f "$f" ] && cp "$f" "$f.bak.$TS"
  curl -fsSL "$U/$f?t=$TS" -o "$f.nuevo" || { echo "ERROR bajando $f"; exit 1; }
  if [[ "$f" == *.php && "$f" != *.blade.php ]]; then php -l "$f.nuevo" >/dev/null || { echo "ERROR de sintaxis en $f"; exit 1; }; fi
  mv "$f.nuevo" "$f"
done
echo "Archivos OK"

# Rutas: dentro del grupo auth/backoffice
W=routes/web.php
if ! grep -q "routes/finanzas.php" $W; then
  cp $W $W.bak.$TS
  python3 - <<'PY' || { echo "ERROR agregando rutas"; exit 1; }
p='routes/web.php'; s=open(p).read()
k="Route::group(['middleware' => ['auth'], 'as' => 'backoffice.'], function () {"
i=s.find(k)
if i<0: raise SystemExit("no encontre el grupo backoffice")
s=s[:i+len(k)]+"\n    require base_path('routes/finanzas.php'); // Finanzas: correos bancarios\n"+s[i+len(k):]
open(p,'w').write(s)
PY
fi
echo "Rutas OK"

# Menu lateral: antes de "F29 Estimado"
M=resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php
if ! grep -q "menu_finanzas" $M; then
  cp $M $M.bak.$TS
  python3 - <<'PY' || { echo "AVISO: no pude agregar el menu (la vista igual queda en /finanzas/correos)"; }
p='resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php'; s=open(p).read()
i=s.find("backoffice.impuesto.index")
if i<0: raise SystemExit(1)
j=s.rfind("<li", 0, i)
s=s[:j]+"@include('themes.backoffice.layouts.includes.menu_finanzas')\n\n                    "+s[j:]
open(p,'w').write(s)
PY
fi
echo "Menu OK"

# Lectura cada 5 minutos
K=app/Console/Kernel.php
if ! grep -q "finanzas:leer-correos" $K; then
  cp $K $K.bak.$TS
  python3 - <<'PY' || { echo "ERROR agregando la tarea"; exit 1; }
p='app/Console/Kernel.php'; s=open(p).read()
k="protected function schedule(Schedule $schedule)\n    {"
i=s.find(k)
if i<0: raise SystemExit("no encontre schedule()")
s=s[:i+len(k)]+"\n        // Finanzas: avisos de transferencias, pagos y cuentas desde el correo (19/10/2026)\n        $schedule->command('finanzas:leer-correos')->everyFiveMinutes()->withoutOverlapping(10);\n"+s[i+len(k):]
open(p,'w').write(s)
PY
  php -l $K >/dev/null || { cp $K.bak.$TS $K; echo "ERROR en Kernel, restaurado"; exit 1; }
fi
echo "Tarea programada OK"

# Tabla
php artisan migrate --path=database/migrations/2026_10_19_200000_create_fin_correos_table.php --force || exit 1

# .env: quien ve Finanzas y datos del correo (la clave la pone Sebastian)
grep -q '^FIN_IMAP_USER=' .env || printf '\n# Finanzas: lectura de correos bancarios\nFIN_IMAP_USER=botacura.info@gmail.com\nFIN_IMAP_PASS=\nFIN_IMAP_FOLDER="[Gmail]/All Mail"\nFIN_IMAP_DIAS=3\n' >> .env
if ! grep -q '^FINANZAS_USUARIOS=' .env; then
  US=$(php artisan tinker --execute="echo DB::table('users')->where(function(\$q){\$q->where('name','like','%sebasti%')->orWhere('name','like','%michelle%');})->pluck('email')->implode(',');" 2>/dev/null | tail -1)
  echo "FINANZAS_USUARIOS=$US" >> .env
fi
php artisan config:clear >/dev/null; php artisan route:clear >/dev/null; php artisan view:clear >/dev/null
echo "----"
echo "Ven Finanzas: $(grep '^FINANZAS_USUARIOS=' .env | cut -d= -f2)"
php artisan route:list --name=finanzas.correos 2>/dev/null | grep -c finanzas.correos | xargs echo "Rutas finanzas.correos:"
if grep -qE '^FIN_IMAP_PASS=.+' .env; then
  php artisan finanzas:leer-correos --prueba --dias=7
else
  echo "FALTA: poner la contrasena de aplicacion de Gmail en FIN_IMAP_PASS (.env) y correr:"
  echo "  php artisan config:clear && php artisan finanzas:leer-correos --prueba --dias=7"
fi
