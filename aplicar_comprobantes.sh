#!/bin/bash
# Comprobantes del bot (13/10/2026):
#  1) tabla comprobantes_revision  2) vista Finanzas > Reservas del dia con aprobacion humana
#  3) el bot revisa el comprobante (destinatario, monto, fecha y hora) antes de crear la reserva
cd /var/www/html/botacurapp || exit 1
rm -rf public/rev-b9e5a57df230   # copia temporal de lectura (tenia datos de la clienta)
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas

# Debe existir aprobar($ventaId) en Verificacion de pagos (se reutiliza para confirmar y avisar al cliente)
grep -qE 'function aprobar\(\s*\$ventaId\s*\)' app/Http/Controllers/VerificacionPagoController.php \
  || { echo "ERROR: VerificacionPagoController::aprobar cambio de forma. No se instalo nada."; exit 1; }

set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" 2>/dev/null <<'SQL' || { echo "ERROR creando la tabla"; exit 1; }
CREATE TABLE IF NOT EXISTS comprobantes_revision (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hold_id INT UNSIGNED NULL,
  reserva_id INT UNSIGNED NULL,
  telefono VARCHAR(20) NOT NULL,
  phone_number_id VARCHAR(40) NULL,
  archivo VARCHAR(255) NULL,
  datos_json TEXT NULL,
  motivos TEXT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  nota VARCHAR(500) NULL,
  revisado_por INT UNSIGNED NULL,
  revisado_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX (estado), INDEX (hold_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
echo "Tabla comprobantes_revision OK"

# Vista Reservas del dia
C=app/Http/Controllers/ReservasDelDiaController.php
V=resources/views/themes/backoffice/pages/reporte/reservas_dia.blade.php
[ -f $C ] && cp $C $C.bak.$(date +%Y%m%d%H%M)
[ -f $V ] && cp $V $V.bak.$(date +%Y%m%d%H%M)
curl -fsSL $U/reservas_dia/ReservasDelDiaController.php -o $C || exit 1
curl -fsSL $U/reservas_dia/reservas_dia.blade.php -o $V || exit 1
curl -fsSL $U/reservas_dia/instalar.py -o /tmp/instalar_reservas_dia.py || exit 1
php -l $C || { echo "ERROR en el controlador"; exit 1; }
python3 /tmp/instalar_reservas_dia.py || exit 1
php -l routes/web.php || { cp "$(ls routes/web.php.bak.* | sort | tail -1)" routes/web.php; echo "ERROR en rutas: restauradas"; exit 1; }

# Bot
F=app/Http/Controllers/Api/WhatsAppWebhookController.php
curl -fsSL $U/bot_comprobante.py -o /tmp/bot_comprobante.py || exit 1
python3 /tmp/bot_comprobante.py || exit 1
php -l $F || { cp "$(ls $F.bak.* | sort | tail -1)" $F; echo "ERROR de sintaxis en el bot: restaurado"; exit 1; }

php artisan view:clear >/dev/null; php artisan route:clear >/dev/null; php artisan config:clear >/dev/null
php artisan tinker --execute="echo strlen(app('blade.compiler')->compileString(file_get_contents('$V'))) > 0 ? 'Vista OK' : 'Vista vacia';" 2>&1 | tail -1
php artisan route:list 2>/dev/null | grep -c reservas-del-dia | sed 's/^/Rutas de Reservas del dia: /'
echo "LISTO: bot revisa comprobantes; lo dudoso queda en Finanzas > Reservas del dia para aprobar o rechazar"
