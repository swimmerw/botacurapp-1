#!/bin/bash
# Menu lateral: bloque "Reservaciones Bot" (10/11/2026)
cd /var/www/html/botacurapp || exit 1
V=resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/menu_bot/instalar.py -o /tmp/menu_bot.py || exit 1
sudo python3 /tmp/menu_bot.py || { rm -f /tmp/menu_bot.py; exit 1; }
rm -f /tmp/menu_bot.py
sudo -u www-data php artisan view:clear >/dev/null
R=$(php artisan tinker --execute="echo strlen(app('blade.compiler')->compileString(file_get_contents('$V'))) > 0 ? 'Vista OK' : 'Vista vacia';" 2>&1 | tail -1)
echo "$R"
if [ "$R" != "Vista OK" ]; then
  B=$(ls $V.bak.* 2>/dev/null | sort | tail -1)
  [ -n "$B" ] && sudo cp "$B" $V && sudo -u www-data php artisan view:clear >/dev/null && echo "Menu restaurado"
  exit 1
fi
for r in verificacion-pago.index comprobantes-sin-reserva.index bot-conversaciones.index bot-configuracion.index; do
  php artisan route:list 2>/dev/null | grep -q "backoffice.$r" && echo "Ruta OK: $r" || echo "Falta la ruta: $r (no aparecera en el menu)"
done
echo "LISTO"
