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
echo "== Log de hoy (comprobantes)"
grep -h "comprobante\|Comprobante\|Hold confirmado\|Vision" storage/logs/laravel*.log 2>/dev/null | grep "$(date +%Y-%m-%d)" | tail -15 | cut -c1-400
echo LISTO
