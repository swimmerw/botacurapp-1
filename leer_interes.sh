#!/bin/bash
# Solo lectura (codigo): registro de fechas pedidas que ya existe en el servidor
cd /var/www/html/botacurapp
D=public/rev-4d92259f9787; mkdir -p $D
cp app/Services/BotInteresFechasService.php $D/interes.txt
grep -rln "BotInteresFechas\|interes" resources/views app/Http/Controllers routes app/Console 2>/dev/null > $D/usos.txt
grep -rn "BotInteresFechas\|interes" routes/web.php app/Console/Kernel.php | cut -c1-220 >> $D/usos.txt
sed -n '1615,1700p' app/Http/Controllers/Api/BotController.php > $D/botctrl_interes.txt
git log -3 --format='%h %ci %s' -- app/Services/BotInteresFechasService.php >> $D/usos.txt 2>/dev/null
ls -la --time-style=long-iso app/Services/BotInteresFechasService.php >> $D/usos.txt
chmod 644 $D/*.txt; ls -la $D; echo LISTO
