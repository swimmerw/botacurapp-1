#!/bin/bash
# Solo lectura (codigo, sin datos de clientes): de donde sale "Conversaciones recientes"
cd /var/www/html/botacurapp
D=public/rev-407c2cbc40ed; mkdir -p $D
cp app/Http/Controllers/BotConfiguracionController.php $D/ctrl.txt
grep -rln "Conversaciones recientes" resources/views > $D/vistas_lista.txt
n=1; for f in $(cat $D/vistas_lista.txt); do cp "$f" $D/vista$n.txt; echo "vista$n = $f" >> $D/vistas_lista.txt; n=$((n+1)); done
grep -rln "class BotAprendizaje\|function embudo\|conversacionesRecientes" app > $D/servicios_lista.txt
n=1; for f in $(cat $D/servicios_lista.txt); do cp "$f" $D/serv$n.txt; echo "serv$n = $f" >> $D/servicios_lista.txt; n=$((n+1)); done
chmod 644 $D/*.txt; ls -la $D; cat $D/vistas_lista.txt $D/servicios_lista.txt; echo LISTO
