#!/bin/bash
# Solo lectura: revisar como el bot valida comprobantes. Borrar la copia despues con: rm -rf /var/www/html/botacurapp/public/rev-b9e5a57df230
cd /var/www/html/botacurapp
D=public/rev-b9e5a57df230; mkdir -p $D
cp app/Http/Controllers/Api/WhatsAppWebhookController.php $D/webhook.txt
cp app/Http/Controllers/Api/BotReservaController.php $D/reserva.txt 2>/dev/null
cp app/Http/Controllers/VerificacionPagoController.php $D/verif.txt 2>/dev/null
chmod 644 $D/*.txt
echo "== Datos bancarios configurados (solo si estan llenos, sin mostrarlos)"
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach((array)config("botacura_pago.datos_bancarios_transferencia") as $k=>$v) echo "$k: ".($v?"lleno (".strlen($v)." caracteres)":"VACIO")."\n";'
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
echo "== Ultimos 5 comprobantes recibidos"
mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT v.id venta, v.id_reserva reserva, v.updated_at, v.estado_pago, v.abono_programa, v.diferencia_programa, v.comprobante_monto monto_leido, v.comprobante_fecha fecha_leida, v.comprobante_tipo_detectado tipo, v.comprobante_alerta alerta FROM ventas v WHERE v.comprobante_transferencia IS NOT NULL ORDER BY v.updated_at DESC LIMIT 5\G" 2>/dev/null

echo "== Conversacion de Camila (se guarda en la copia temporal, no en pantalla)"
Q() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$1" 2>/dev/null; }
U=$(Q "SELECT usuario_id FROM bot_conversaciones WHERE nombre_cliente LIKE '%camila%' ORDER BY updated_at DESC LIMIT 1" -N | tail -1)
[ -z "$U" ] && U=$(Q "SELECT c.whatsapp_cliente FROM clientes c JOIN reservas r ON r.cliente_id=c.id WHERE c.nombre_cliente LIKE '%camila%' ORDER BY r.created_at DESC LIMIT 1" -N | tail -1)
echo "usuario encontrado: ${U:0:6}..."
{
echo "### bot_conversaciones"; Q "SELECT * FROM bot_conversaciones WHERE usuario_id='$U' ORDER BY id DESC LIMIT 2\G"
echo "### bot_eventos"; Q "SELECT * FROM bot_eventos WHERE usuario_id='$U' OR telefono='$U' ORDER BY id DESC LIMIT 120\G"
echo "### reserva_holds"; Q "SELECT * FROM reserva_holds WHERE telefono='$U' ORDER BY id DESC LIMIT 3\G"
echo "### reservas y ventas"; Q "SELECT r.id reserva, r.created_at, r.fecha_visita, r.cantidad_personas, v.* FROM reservas r JOIN clientes c ON c.id=r.cliente_id LEFT JOIN ventas v ON v.id_reserva=r.id WHERE c.whatsapp_cliente='$U' ORDER BY r.id DESC LIMIT 3\G"
} > $D/camila.txt
chmod 644 $D/camila.txt; wc -l $D/camila.txt
echo "== Log de hoy (comprobantes)"
grep -h "comprobante\|Comprobante\|Hold confirmado\|Vision" storage/logs/laravel*.log 2>/dev/null | grep "$(date +%Y-%m-%d)" | tail -15 | cut -c1-400
echo LISTO
