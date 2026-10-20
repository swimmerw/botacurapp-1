#!/bin/bash
# Solo lectura (codigo y textos de configuracion del bot, sin datos de clientes)
cd /var/www/html/botacurapp
rm -rf public/rev-83a74f8055dd
D=public/rev-a39a2c2b568b; mkdir -p $D
sed -n '140,260p' app/Services/BotTarifasService.php > $D/tarifas.txt
sed -n '1430,1470p' app/Http/Controllers/Api/BotController.php > $D/botctrl.txt
sed -n '790,830p' app/Services/BotPromptService.php > $D/prompt.txt
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT clave, contenido FROM bot_secciones_texto WHERE clave IN ('regla_apertura_agenda','horarios')\G" 2>/dev/null > $D/textos.txt
ls -la storage/logs | tail -5 > $D/logs.txt
grep -h "ERROR" storage/logs/*.log 2>/dev/null | grep -i "secciones\|SeccionTexto\|updateSecciones" | tail -3 | cut -c1-700 >> $D/logs.txt
sudo tail -n 400 /var/log/apache2/error.log 2>/dev/null | grep -i "secciones\|Fatal\|PHP" | tail -5 | cut -c1-500 >> $D/logs.txt
sudo tail -n 400 /var/log/nginx/error.log 2>/dev/null | grep -i "secciones\|Fatal\|PHP" | tail -5 | cut -c1-500 >> $D/logs.txt
chmod 644 $D/*.txt; ls -la $D; echo LISTO
