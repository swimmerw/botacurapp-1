#!/bin/bash
# Finanzas fase 1 (19/10/2026): lectura automatica de correos bancarios.
#  - Abonos recibidos, pagos, traspasos entre cuentas, cuentas por pagar, cartolas y facturas.
#  - Cada correo se verifica con la firma DKIM del banco (dominio oficial): solo los verificados se cruzan solos.
#  - Buzones: botacura.info@gmail.com y (opcional) el personal de Sebastian.
#  - Vista Finanzas > Correos bancarios (solo FINANZAS_USUARIOS), lectura cada 5 minutos
#    y marca "en el banco" en Finanzas > Reservas del dia.
# No cambia estados de ventas ni reservas: solo lee, guarda y marca.
cd /var/www/html/botacurapp || exit 1
# Version fija del ultimo cambio (evita la cache de GitHub)
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/correos
echo "Version: ${SHA:0:7}"
F="config/finanzas.php app/Finanzas/Correo/ImapCliente.php app/Finanzas/Correo/Mime.php app/Finanzas/Correo/Lector.php app/Finanzas/Correo/ConciliadorAbonos.php app/Finanzas/Correo/Dkim.php app/Finanzas/Correo/Seguridad.php app/Console/Commands/LeerCorreosBancarios.php app/Http/Controllers/FinanzasCorreoController.php routes/finanzas.php database/migrations/2026_10_19_200000_create_fin_correos_table.php database/migrations/2026_10_19_210000_add_seguridad_to_fin_correos.php database/migrations/2026_10_19_220000_add_buzon_to_fin_correos.php resources/views/themes/backoffice/pages/finanzas_correos/index.blade.php resources/views/themes/backoffice/pages/finanzas_correos/show.blade.php resources/views/themes/backoffice/pages/finanzas_correos/_badge.blade.php resources/views/themes/backoffice/pages/finanzas_correos/_dia.blade.php resources/views/themes/backoffice/layouts/includes/menu_finanzas.blade.php"
TS=$(date +%Y%m%d%H%M)
for f in $F; do
  mkdir -p "$(dirname "$f")"
  curl -fsSL "$U/$f" -o "$f.nuevo" || { echo "ERROR bajando $f"; exit 1; }
  if [[ "$f" == *.php && "$f" != *.blade.php ]]; then php -l "$f.nuevo" >/dev/null || { echo "ERROR de sintaxis en $f"; rm -f "$f.nuevo"; exit 1; }; fi
  if [ -f "$f" ] && cmp -s "$f" "$f.nuevo"; then rm -f "$f.nuevo"; continue; fi
  [ -f "$f" ] && cp "$f" "$f.bak.$TS"
  mv "$f.nuevo" "$f"
done
echo "Archivos OK"

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
M=resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php
if ! grep -q "menu_finanzas" $M; then
  cp $M $M.bak.$TS
  python3 - <<'PY' || echo "AVISO: no pude agregar el menu (la vista igual queda en /finanzas/correos)"
p='resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php'; s=open(p).read()
i=s.find("backoffice.impuesto.index")
if i<0: raise SystemExit(1)
j=s.rfind("<li", 0, i)
s=s[:j]+"@include('themes.backoffice.layouts.includes.menu_finanzas')\n\n                    "+s[j:]
open(p,'w').write(s)
PY
fi
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
echo "Rutas, menu y tarea OK"

# Reservas del dia: marca "en el banco"
C=app/Http/Controllers/ReservasDelDiaController.php
V=resources/views/themes/backoffice/pages/reporte/reservas_dia.blade.php
if [ -f $C ] && [ -f $V ]; then
  cp $C $C.bak.$TS; cp $V $V.bak.$TS
  curl -fsSL "$U/instalar_reservas_dia.py" -o /tmp/instalar_reservas_dia.py && python3 /tmp/instalar_reservas_dia.py \
    && php -l $C >/dev/null || { cp $C.bak.$TS $C; cp $V.bak.$TS $V; echo "AVISO: Reservas del dia sin cambios (restaurado)"; }
fi

for m in 2026_10_19_200000_create_fin_correos_table 2026_10_19_210000_add_seguridad_to_fin_correos 2026_10_19_220000_add_buzon_to_fin_correos; do
  php artisan migrate --path=database/migrations/$m.php --force | grep -v "Nothing to migrate" || true
done

grep -q '^FIN_IMAP_USER=' .env || printf '\n# Finanzas: lectura de correos bancarios\nFIN_IMAP_USER=botacura.info@gmail.com\nFIN_IMAP_PASS=\nFIN_IMAP_FOLDER="[Gmail]/All Mail"\nFIN_IMAP_DIAS=3\n' >> .env
grep -q '^FIN_IMAP2_USER=' .env || printf 'FIN_IMAP2_USER=swimmerw@gmail.com\nFIN_IMAP2_PASS=\n' >> .env
if ! grep -q '^FINANZAS_USUARIOS=' .env; then
  US=$(php artisan tinker --execute="echo DB::table('users')->where(function(\$q){\$q->where('name','like','%sebasti%')->orWhere('name','like','%michelle%');})->pluck('email')->implode(',');" 2>/dev/null | tail -1)
  echo "FINANZAS_USUARIOS=$US" >> .env
fi
if ! grep -q '^FINANZAS_PERSONAL=' .env; then
  YO=$(php artisan tinker --execute="echo DB::table('users')->where('name','like','%sebasti%')->pluck('email')->implode(',');" 2>/dev/null | tail -1)
  echo "FINANZAS_PERSONAL=$YO" >> .env
fi
php artisan config:clear >/dev/null; php artisan route:clear >/dev/null; php artisan view:clear >/dev/null
echo "----"
echo "Ven Finanzas: $(grep '^FINANZAS_USUARIOS=' .env | cut -d= -f2)"
echo "Gastos personales los ve: $(grep '^FINANZAS_PERSONAL=' .env | cut -d= -f2)"
php artisan finanzas:leer-correos --releer
php artisan finanzas:leer-correos --prueba --dias=7
