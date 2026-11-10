#!/bin/bash
# Avisos de WhatsApp al aprobar/rechazar comprobantes: registro de entrega, plantilla fuera de 24 h y cola (10/11/2026)
cd /var/www/html/botacurapp || exit 1
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/envios
S=app/Services/WaEnviosService.php
P=resources/views/themes/backoffice/pages/verificacion_pago/_entregas.blade.php
for f in app/Http/Controllers/VerificacionPagoController.php app/Http/Controllers/Api/WhatsAppWebhookController.php resources/views/themes/backoffice/pages/verificacion_pago/index.blade.php; do
  [ -f $f ] || { echo "ERROR: falta $f. No se instalo nada."; exit 1; }
done

echo "== 1/4 Tablas"
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" 2>/dev/null <<'SQL' || { echo "ERROR creando las tablas"; exit 1; }
CREATE TABLE IF NOT EXISTS wa_envios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wamid VARCHAR(120) NULL,
  telefono VARCHAR(30) NOT NULL,
  reserva_id INT UNSIGNED NULL,
  venta_id INT UNSIGNED NULL,
  tipo VARCHAR(30) NOT NULL,
  via VARCHAR(15) NOT NULL,
  payload_json MEDIUMTEXT NULL,
  estado VARCHAR(20) NOT NULL,
  error_code VARCHAR(20) NULL,
  error_text VARCHAR(500) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX (wamid), INDEX (telefono, estado), INDEX (reserva_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS wa_entrantes (
  telefono VARCHAR(30) NOT NULL PRIMARY KEY,
  ultimo_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
echo "Tablas OK"

echo "== 2/4 Archivos"
sudo curl -fsSL $R/WaEnviosService.php -o $S || exit 1
sudo curl -fsSL $R/_entregas.blade.php -o $P || exit 1
curl -fsSL $R/notificar.php -o /tmp/wae_notificar.php || exit 1
php -l $S >/dev/null || { echo "ERROR de sintaxis en el servicio"; sudo rm -f $S $P; exit 1; }
curl -fsSL $R/instalar.py -o /tmp/wae_instalar.py || exit 1
OUT=$(sudo python3 /tmp/wae_instalar.py); RC=$?; echo "$OUT"; rm -f /tmp/wae_instalar.py /tmp/wae_notificar.php
[ $RC -eq 0 ] || { sudo rm -f $S $P; exit 1; }
TS=$(echo "$OUT" | grep '^BAK ' | awk '{print $2}')
restaurar() {
  for f in app/Http/Controllers/VerificacionPagoController.php app/Http/Controllers/Api/WhatsAppWebhookController.php resources/views/themes/backoffice/pages/verificacion_pago/index.blade.php; do
    [ -n "$TS" ] && [ -f $f.bak.$TS ] && sudo cp $f.bak.$TS $f
  done
  sudo rm -f $P; sudo -u www-data php artisan view:clear >/dev/null; echo "Cambios deshechos: todo quedo como antes"
}
for f in app/Http/Controllers/VerificacionPagoController.php app/Http/Controllers/Api/WhatsAppWebhookController.php; do
  php -l $f >/dev/null || { echo "ERROR de sintaxis en $f"; restaurar; exit 1; }
done
for f in $P resources/views/themes/backoffice/pages/verificacion_pago/index.blade.php; do
  php artisan tinker --execute="file_put_contents('/tmp/wae_v.php', app('blade.compiler')->compileString(file_get_contents('$f')));" >/dev/null 2>&1
  php -l /tmp/wae_v.php >/dev/null 2>&1 || { echo "ERROR en la vista $f"; rm -f /tmp/wae_v.php; restaurar; exit 1; }
done
rm -f /tmp/wae_v.php
sudo -u www-data php artisan view:clear >/dev/null
echo "Codigo OK"

echo "== 3/4 Plantillas de WhatsApp"
curl -fsSL $R/plantillas.php -o /tmp/wae_pl.php && sudo -u www-data php /tmp/wae_pl.php; rm -f /tmp/wae_pl.php

echo "== 4/4 Prueba de la pantalla"
php artisan tinker --execute="echo 'Ventas por verificar con reloj de 24 h: ' . count(App\Services\WaEnviosService::horasPorVenta()) . PHP_EOL;" 2>&1 | tail -1
echo "LISTO"
