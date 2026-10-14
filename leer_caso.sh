#!/bin/bash
# Solo lectura (codigo): historial del caso y carga de conversacion del bot
cd /var/www/html/botacurapp
rm -rf public/rev-407c2cbc40ed
D=public/rev-83a74f8055dd; mkdir -p $D
cp app/Services/BotCasoService.php $D/caso.txt
grep -n "function obtenerConversacion" -A40 app/Http/Controllers/Api/BotController.php > $D/obtener.txt
grep -n "BotCasoService::" app/Http/Controllers/Api/BotController.php app/Services/BotPromptService.php | head -20 >> $D/obtener.txt
chmod 644 $D/*.txt; ls -la $D; echo LISTO
