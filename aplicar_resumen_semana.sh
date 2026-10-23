#!/bin/bash
# Resumen semanal por el WhatsApp de avisos (HA): el día antes del primer día con reservas de la semana, 14:55.
# Botones en Equipo › Turnos sugeridos: "Enviar resumen de la semana" y "Avisar turnos al equipo".
cd /var/www/html/botacurapp || exit 1
if [ ! -f app/Equipo/Turnos.php ]; then echo "Instalando primero Turnos sugeridos..."; curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/aplicar_turnos.sh | bash || exit 1; cd /var/www/html/botacurapp; fi
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/semana
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M); T=$(mktemp -d)
EDIT="app/Equipo/Turnos.php app/Http/Controllers/EquipoTurnosController.php routes/equipo.php resources/views/themes/backoffice/pages/equipo_turnos/index.blade.php app/Console/Kernel.php"
for f in $EDIT; do cp "$f" "$f.bak.$TS"; done
curl -fsSL $R/patch.py -o $T/patch.py && curl -fsSL $R/metodos.php.txt -o $T/metodos.php.txt \
 && curl -fsSL $R/app/Console/Commands/EquipoResumenSemana.php -o app/Console/Commands/EquipoResumenSemana.php || { echo "ERROR bajando"; exit 1; }
ok=1; python3 $T/patch.py "$PWD" $T || ok=0
for f in app/Equipo/Turnos.php app/Http/Controllers/EquipoTurnosController.php routes/equipo.php app/Console/Kernel.php app/Console/Commands/EquipoResumenSemana.php; do php -l $f >/dev/null || ok=0; done
if [ $ok = 0 ]; then for f in $EDIT; do cp "$f.bak.$TS" "$f"; done; rm -f app/Console/Commands/EquipoResumenSemana.php; echo "ERROR: restaurado"; exit 1; fi
rm -rf $T; php artisan route:clear >/dev/null; php artisan view:clear >/dev/null
echo "--- Vista previa (no envía) ---"
php artisan equipo:resumen-semana --desde="${1:-$(date -d 'next monday' +%F)}"
php artisan schedule:list 2>/dev/null | grep -i resumen || grep -n "resumen-semana" app/Console/Kernel.php
echo LISTO
