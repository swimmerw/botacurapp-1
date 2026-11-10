#!/bin/bash
# Solo lectura (codigo y guion del bot, sin datos de clientes): orden del flujo de reserva (10/11/2026)
# Borrar la copia despues con: rm -rf /var/www/html/botacurapp/public/rev-2ef74d3f5b35
cd /var/www/html/botacurapp || exit 1
D=public/rev-2ef74d3f5b35; mkdir -p $D
for f in app/Services/BotPromptService.php app/Http/Controllers/Api/BotController.php app/Http/Controllers/Api/BotReservaController.php app/Services/DisponibilidadService.php app/Services/BotUbicacionesService.php app/Services/BotTarifasService.php app/Services/BotCasoService.php; do
  [ -f "$f" ] && cp "$f" $D/$(basename "$f" .php).txt
done
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
M() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" "$@" 2>/dev/null; }
M -e "SELECT * FROM bot_pasos_flujo ORDER BY numero_paso\G" > $D/pasos.txt
M -e "SELECT clave, contenido FROM bot_secciones_texto ORDER BY clave\G" > $D/secciones.txt
chmod 644 $D/*.txt; ls -la $D | tail -n +2
echo "== Pasos del guion"
M -e "SELECT id, numero_paso, LEFT(REPLACE(instrucciones,'\n',' '),110) texto FROM bot_pasos_flujo ORDER BY numero_paso"
echo LISTO
