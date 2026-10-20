#!/bin/bash
# Solo lectura (codigo, sin datos de clientes): donde el bot detecta dias no habilitados
cd /var/www/html/botacurapp
rm -rf public/rev-a39a2c2b568b
D=public/rev-4d92259f9787; mkdir -p $D
grep -n "habilitad\|FechaDisponible\|fecha_no_habilitada" app/Http/Controllers/Api/BotController.php app/Services/*.php app/Http/Controllers/Api/BotReservaController.php | cut -c1-260 > $D/donde.txt
grep -n "function message" -A25 app/Http/Controllers/Api/BotController.php > $D/message.txt
grep -n "case 'verificar_disponibilidad'\|verificar_disponibilidad'" -A30 app/Http/Controllers/Api/BotController.php | head -80 > $D/disp.txt
cat app/FechaDisponible.php > $D/modelo.txt 2>/dev/null
grep -rn "fechas_disponibles\|FechaDisponible" routes/web.php app/Http/Controllers/*.php | grep -v "^app/Http/Controllers/Api" | cut -c1-200 | head -15 >> $D/modelo.txt
grep -n "HA_\|META_\|BOT_" .env | cut -d= -f1 > $D/env_claves.txt
chmod 644 $D/*.txt; ls -la $D; echo LISTO
