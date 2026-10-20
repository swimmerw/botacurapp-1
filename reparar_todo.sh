#!/bin/bash
# BotacurApp (AWS): permisos de storage + reinstalar los cambios del 15/10 que desaparecieron. 17/10/2026
# Correr como ubuntu (usa sudo donde hace falta).
cd /var/www/html/botacurapp || exit 1
echo "=== Estado de git (para saber que paso)"
git log --oneline -5; echo "--- stash:"; git stash list | head -5; echo "--- modificados:"; git status --short | head -40
echo "=== Permisos: ubuntu y www-data comparten grupo y pueden escribir storage y bootstrap/cache"
sudo usermod -a -G www-data ubuntu
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R g+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} +
grep -q "'permission' => 0664" config/logging.php || sudo sed -i "s/'days' => 14,/'days' => 14,\n            'permission' => 0664,/" config/logging.php
php -l config/logging.php >/dev/null && echo "permisos OK"
echo "=== Reinstalar (cada uno se salta si ya esta)"
B=https://raw.githubusercontent.com/swimmerw/botacurapp-1
for par in reserva_multiprograma:2591d9cf23bd9c1df917d794f278eec1ed4f3676 masajes_admin:e1b6688ad548e837b382cfdc062ba4db293b3545 multiprograma_boleta:af2914281dd3f3dcff29b39b7614a352e057c955 multiprograma_ubicaciones:3a8ef78197ad499c8f7b92125075bac81cee68a5 giftcard_vigencia:0f3b9c1163ce5b1a98ff96fc611e42f273144926; do
  n=${par%%:*}; c=${par##*:}; s=$c; [ $n = giftcard_vigencia ] && s=d2aefffc2412a4a8bfedf09da7292a403d6032a7
  echo "--- $n"
  curl -fsSL "$B/$s/$n.sh" -o /tmp/inst_$n.sh && sg www-data -c "bash /tmp/inst_$n.sh $c" 2>&1 | grep -v "^ *$" | tail -8
done
echo "=== Verificacion"
for p in detalleProgramas personasConMasaje personasDesayunoOnce scopeConPrograma tieneEstacionExtra; do echo "$p: $(grep -c "function $p" app/Reserva.php)"; done
echo "fecha_compra en GiftCard: $(grep -c fecha_compra app/GiftCard.php)"
echo "--- Otros cambios de dias anteriores:"
for f in app/Services/BotInteresFechasService.php app/Http/Controllers/InteresFechasCorreoController.php app/Http/Controllers/ReservasDelDiaController.php; do [ -f $f ] && echo "OK  $f" || echo "FALTA $f"; done
grep -q "esReinicioDelEquipo" app/Http/Controllers/Api/WhatsAppWebhookController.php && echo "OK  #reiniciar del bot" || echo "FALTA #reiniciar del bot"
grep -q "clasificarComprobante" app/Http/Controllers/Api/WhatsAppWebhookController.php && echo "OK  revision de comprobantes" || echo "FALTA revision de comprobantes"
echo "=== Vista previa del reporte de mañana (no envia nada)"
sudo -u www-data php artisan botacura:reporte-manana --probar
