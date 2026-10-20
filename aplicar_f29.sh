#!/bin/bash
# F29 en Por pagar: cada mes (IVA a pagar + retención de honorarios + PPM, igual que la vista Impuestos) queda como Impuesto
# con vencimiento el 20 del mes siguiente. Este instalador SOLO muestra; para cargar, ver las instrucciones al final.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/f29
TS=$(date +%Y%m%d%H%M)
C=app/Console/Commands/FinanzasF29Deudas.php
curl -fsSL "$U/$C" -o $C.nuevo && php -l $C.nuevo >/dev/null && mv $C.nuevo $C || { rm -f $C.nuevo; echo "ERROR bajando el comando"; exit 1; }
K=app/Console/Kernel.php
if ! grep -q "finanzas:f29-deudas" $K; then
  cp $K $K.bak.$TS
  python3 - <<'PY' || { cp $K.bak.$TS $K; echo "ERROR en Kernel"; exit 1; }
import re
p='app/Console/Kernel.php'; s=open(p).read()
m=re.search(r'protected function schedule\(Schedule \$schedule\)\s*\{\n', s)
if not m: raise SystemExit('sin schedule()')
s=s[:m.end()]+"        // F29 del mes anterior a Por pagar (después de la sincronización del SII)\n        $schedule->command('finanzas:f29-deudas --aplicar')->dailyAt('11:15')->timezone('America/Santiago');\n"+s[m.end():]
open(p,'w').write(s)
PY
  php -l $K >/dev/null || { cp $K.bak.$TS $K; echo "ERROR de sintaxis en Kernel, restaurado"; exit 1; }
fi
php artisan config:clear >/dev/null
echo "F29 de cada mes según el SII (simulación, no guarda nada):"
php artisan finanzas:f29-deudas --desde=2026-01
