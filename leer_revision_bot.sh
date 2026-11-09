#!/bin/bash
# Solo lectura (09/11/2026): copia el codigo del bot y la lista de conversaciones pendientes
# a una carpeta temporal con nombre aleatorio para revisarlos. Borrar despues con el comando que imprime al final.
cd /var/www/html/botacurapp || exit 1
T=$(openssl rand -hex 10); D=public/rev-$T; mkdir -p $D
for f in app/Http/Controllers/Api/WhatsAppWebhookController.php app/Http/Controllers/Api/BotReservaController.php \
         app/Http/Controllers/Api/BotController.php app/Http/Controllers/VerificacionPagoController.php; do
  [ -f "$f" ] && cp "$f" "$D/$(basename $f .php).txt"
done
grep -rl "reserva_holds\|expirado" app --include=*.php | grep -v "Api/\(WhatsAppWebhook\|BotReserva\|Bot\)Controller" | head -12 > $D/_otros.txt
while read f; do cp "$f" "$D/otro_$(basename $f .php).txt"; done < $D/_otros.txt
grep -n "bot\|whatsapp\|hold" routes/api.php > $D/rutas_api.txt 2>/dev/null
grep -n "schedule\|command" app/Console/Kernel.php > $D/kernel.txt 2>/dev/null
grep -E '^(BOT_|META_API_VERSION|HA_PHONE|META_PHONE)' .env | sed -E 's/(TOKEN|SECRET|KEY)=.*/\1=***oculto***/' > $D/env_bot.txt
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"\r'); set +a
Q() { mysql -h"${DB_HOST:-127.0.0.1}" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$1" 2>&1; }
{
echo "### columnas"; Q "SELECT TABLE_NAME, GROUP_CONCAT(CONCAT(COLUMN_NAME,':',COLUMN_TYPE)) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bot_conversaciones','reserva_holds','ventas','reservas','comprobantes_revision','bot_eventos','wa_historial') GROUP BY TABLE_NAME\G"
echo "### conversaciones WhatsApp/Instagram de las ultimas 48 h: quien hablo ultimo"
Q "SELECT id, canal, usuario_id, requiere_humano, humano_hasta, updated_at,
   JSON_UNQUOTE(JSON_EXTRACT(historial_json, CONCAT('\$[', JSON_LENGTH(historial_json)-1, '].role'))) ultimo_rol,
   REPLACE(LEFT(JSON_UNQUOTE(JSON_EXTRACT(historial_json, CONCAT('\$[', JSON_LENGTH(historial_json)-1, '].content'))),260),'\n',' ') ultimo
   FROM bot_conversaciones WHERE updated_at >= NOW() - INTERVAL 48 HOUR ORDER BY updated_at DESC\G"
echo "### historial del celular: clientes cuyo ultimo mensaje es del cliente (ultimos 7 dias)"
Q "SELECT h.cliente, h.enviado_at, h.tipo, REPLACE(LEFT(h.texto,200),'\n',' ') texto FROM wa_historial h
   JOIN (SELECT cliente, MAX(enviado_at) m FROM wa_historial GROUP BY cliente) u ON u.cliente=h.cliente AND u.m=h.enviado_at
   WHERE h.desde_negocio=0 AND h.enviado_at >= NOW() - INTERVAL 7 DAY ORDER BY h.enviado_at DESC"
echo "### historial: resumen"; Q "SELECT COUNT(*) mensajes, COUNT(DISTINCT cliente) clientes, MIN(enviado_at), MAX(enviado_at) FROM wa_historial"
echo "### holds ultimos 7 dias"; Q "SELECT id, telefono, nombre, fecha, personas, valor_total, estado, expira_en, id_reserva, created_at FROM reserva_holds WHERE created_at >= NOW() - INTERVAL 7 DAY"
echo "### comprobantes_revision recientes"; Q "SELECT * FROM comprobantes_revision ORDER BY id DESC LIMIT 15\G"
} > $D/datos.txt
echo "### log de hoy (bot y webhook, sin historial)" > $D/log_hoy.txt
grep -h "$(date +%Y-%m-%d)" storage/logs/laravel-$(date +%Y-%m-%d).log | grep -v "WA sync\|Verificación fallida" | grep -i "whatsapp\|bot\|instagram\|hold\|comprobante" | cut -c1-600 >> $D/log_hoy.txt
chmod 644 $D/*; ls -la $D | tail -n +2 | awk '{print $5, $9}'
echo "URL: https://app.botacura.cl/rev-$T/"
echo "Borrar despues: rm -rf /var/www/html/botacurapp/$D"
