#!/bin/bash
# Reservaciones Bot > Atención de ejecutivo (10/11/2026)
cd /var/www/html/botacurapp || exit 1
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/ejecutivo
V=resources/views/themes/backoffice/pages/bot-ejecutivo
C=app/Http/Controllers/BotEjecutivoController.php
[ -f app/BotMensajePendiente.php ] || { echo "ERROR: no existe el modelo BotMensajePendiente. No se instalo nada."; exit 1; }

# 1) Controlador y vistas (todavia sin ruta ni menu: nadie las ve)
sudo mkdir -p $V
sudo curl -fsSL $R/BotEjecutivoController.php -o $C || exit 1
sudo curl -fsSL $R/index.blade.php -o $V/index.blade.php || exit 1
sudo curl -fsSL $R/_chat.blade.php -o $V/_chat.blade.php || exit 1
php -l $C >/dev/null || { echo "ERROR de sintaxis en el controlador"; sudo rm -rf $C $V; exit 1; }
for f in $V/index.blade.php $V/_chat.blade.php; do
  php artisan tinker --execute="file_put_contents('/tmp/bej_vista.php', app('blade.compiler')->compileString(file_get_contents('$f')));" >/dev/null 2>&1
  php -l /tmp/bej_vista.php >/dev/null 2>&1 || { echo "ERROR en la vista $f"; sudo rm -rf $C $V; rm -f /tmp/bej_vista.php; exit 1; }
done
rm -f /tmp/bej_vista.php
echo "Vistas OK"

# 2) Rutas y opcion del menu
curl -fsSL $R/instalar.py -o /tmp/bej_instalar.py && sudo python3 /tmp/bej_instalar.py || { rm -f /tmp/bej_instalar.py; exit 1; }
rm -f /tmp/bej_instalar.py
php -l routes/web.php >/dev/null || { sudo cp "$(ls routes/web.php.bak.* | sort | tail -1)" routes/web.php; echo "ERROR en rutas: restauradas"; exit 1; }
M=resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php
php artisan tinker --execute="file_put_contents('/tmp/bej_menu.php', app('blade.compiler')->compileString(file_get_contents('$M')));" >/dev/null 2>&1
php -l /tmp/bej_menu.php >/dev/null 2>&1 || { sudo cp "$(ls $M.bak.* | sort | tail -1)" $M; echo "ERROR en el menu: restaurado"; rm -f /tmp/bej_menu.php; exit 1; }
rm -f /tmp/bej_menu.php
sudo -u www-data php artisan view:clear >/dev/null; sudo -u www-data php artisan route:clear >/dev/null

# 3) Diagnostico
php artisan tinker --execute="
\$p = App\BotMensajePendiente::where('estado','pendiente')->get();
echo 'Pendientes por tipo: ' . \$p->groupBy('tipo')->map->count()->toJson() . PHP_EOL;
echo 'Chats derivados detectados: ' . \$p->filter(function(\$m){ return App\Http\Controllers\BotEjecutivoController::esDerivado(\$m); })->pluck('telefono')->unique()->count() . PHP_EOL;
" 2>&1 | tail -2
php artisan route:list 2>/dev/null | grep -q "backoffice.bot-ejecutivo.index" && echo "Ruta OK: /bot-ejecutivo"
echo "LISTO"
