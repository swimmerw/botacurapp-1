#!/bin/bash
# aplicar_giftcard_web.sh — 06/11/2026
# La Gift Card comprada en botacura.cl (producto gift-card-botacura, $220.000 por pareja)
# queda registrada en el módulo Gift Cards (código, 45 días de vigencia) en vez de crear una reserva.
set -uo pipefail
APP=/var/www/html/botacurapp
cd "$APP" || { echo "No existe $APP"; exit 1; }
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}
TS=$(date +%Y%m%d%H%M%S)
W=app/Http/Controllers/Api/WoocommerceWebhookController.php
S=app/Services/GiftCardWebService.php
[ -f "$W" ] || { echo "No encuentro $W"; exit 1; }
cp -p "$W" "$W.bak.$TS"; [ -f "$S" ] && cp -p "$S" "$S.bak.$TS"
restaurar() { echo "!! Restaurando…"; cp -p "$W.bak.$TS" "$W"; if [ -f "$S.bak.$TS" ]; then cp -p "$S.bak.$TS" "$S"; else rm -f "$S"; fi; php artisan route:clear >/dev/null 2>&1; echo "!! Quedó como estaba. Pega esta salida en el chat."; exit 1; }
# Requisito: webhook que acepta pagos Webpay (aplicar_ventas_web.sh)
if ! grep -q ESTADOS_PAGADOS "$W"; then
  curl -fsSL $R/wcpago/patch.py -o /tmp/gcw0.py && python3 /tmp/gcw0.py "$PWD" || restaurar
fi
curl -fsSL $R/gcweb/app/Services/GiftCardWebService.php -o "$S" || restaurar
curl -fsSL $R/gcweb/patch.py -o /tmp/gcw1.py && python3 /tmp/gcw1.py "$PWD" || restaurar
rm -f /tmp/gcw0.py /tmp/gcw1.py
for f in "$W" "$S"; do php -l "$f" >/dev/null || { php -l "$f"; restaurar; }; done
php artisan tinker --execute="
\$o = ['line_items' => [['sku' => 'gift-card-botacura', 'quantity' => 1, 'total' => '220000']]];
echo 'Detecta Gift Card: ' . (App\Services\GiftCardWebService::esPedidoGiftCard(\$o) ? 'sí' : 'NO') . PHP_EOL;
\$p = DB::table('programas')->where('nombre_programa', 'Gift Card Botacura Full')->first();
echo 'Programa Gift Card: ' . (\$p ? '#' . \$p->id . ' \$' . number_format(\$p->valor_programa, 0, ',', '.') . ' por persona' : 'NO EXISTE (usará Botacura Full)') . PHP_EOL;
echo 'Columnas gift_cards: ' . implode(', ', Schema::getColumnListing('gift_cards')) . PHP_EOL;
" 2>&1 | grep -v "^>"
php artisan route:clear >/dev/null; php artisan config:clear >/dev/null; php artisan queue:restart >/dev/null 2>&1 || true
echo "LISTO"
