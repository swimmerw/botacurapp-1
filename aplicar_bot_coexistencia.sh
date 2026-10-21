#!/bin/bash
# Bot-Acura:
#  1) Banderín "BOT" en las reservas hechas por el bot (vista del día, registro por día y Finanzas › Reservas del día).
#  2) Encender o apagar el bot por conversación desde Conversaciones del bot (no apaga todo WhatsApp).
#  3) Coexistencia del 7448 4112: muestra el estado y, con el phone_number_id que entrega la página Conectar,
#     deja el bot respondiendo desde ese número:  ... | bash -s -- <phone_number_id>
cd /var/www/html/botacurapp || exit 1
NUEVO="$1"
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/bot
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
for f in resources/views/themes/backoffice/pages/reserva/includes/_bandera_bot.blade.php database/migrations/2026_10_21_200000_create_bot_chats_pausados.php app/Http/Controllers/BotChatPausaController.php; do
  mkdir -p "$(dirname $f)"; curl -fsSL "$U/$f" -o "$f.nuevo" || { echo "ERROR bajando $f"; exit 1; }
  case $f in *.php) case $f in *.blade.php) ;; *) php -l "$f.nuevo" >/dev/null || { echo "ERROR de sintaxis en $f"; rm -f "$f.nuevo"; exit 1; } ;; esac ;; esac
  [ -f "$f" ] && cp "$f" "$f.bak.$TS"; mv "$f.nuevo" "$f"
done
V=resources/views/themes/backoffice/pages
PATCHED="$V/reserva/screens/principal.blade.php $V/reserva/screens/mobile.blade.php $V/reserva/screens/alternative.blade.php $V/reserva/index_registro.blade.php $V/reporte/reservas_dia.blade.php app/Http/Controllers/Api/WhatsAppWebhookController.php routes/web.php $V/bot-conversaciones/index.blade.php"
for f in $PATCHED; do [ -f $f ] && cp $f $f.bak.$TS; done
restaurar() { for f in $PATCHED; do [ -f $f.bak.$TS ] && cp $f.bak.$TS $f; done; echo "ERROR: $1 (restaurado)"; exit 1; }
echo "---- Banderín BOT"
curl -fsSL "$U/patch_bandera.py" | python3 - || restaurar "banderín"
echo "---- Bot por conversación"
php artisan migrate --force --path=database/migrations/2026_10_21_200000_create_bot_chats_pausados.php | tail -1
curl -fsSL "$U/patch_pausa.py" | python3 - || restaurar "bot por conversación"
php -l app/Http/Controllers/Api/WhatsAppWebhookController.php >/dev/null && php -l routes/web.php >/dev/null || restaurar "sintaxis"
php artisan route:clear >/dev/null; php artisan view:clear >/dev/null

echo "---- Coexistencia"
curl -fsSL "$U/numeros.php" -o numeros_tmp.php && php numeros_tmp.php; rm -f numeros_tmp.php
leer() { grep -m1 "^$1=" .env | cut -d= -f2- | tr -d '"'; }
echo "  Bot de reservas responde desde: $(leer META_PHONE_NUMBER_ID) · Home Assistant: $(leer HA_PHONE_NUMBER_ID) · HA_TELEFONOS: $(leer HA_TELEFONOS)"
if [ -n "$NUEVO" ]; then
  INFO=$(php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $r=(new GuzzleHttp\Client(["http_errors"=>false,"timeout"=>15]))->get("https://graph.facebook.com/".env("META_API_VERSION","v19.0")."/'"$NUEVO"'", ["query"=>["fields"=>"display_phone_number,verified_name,is_on_biz_app,platform_type,code_verification_status,quality_rating","access_token"=>env("META_WHATSAPP_TOKEN")]]);
    echo (string)$r->getBody();')
  echo "  Número nuevo: $INFO"
  if ! echo "$INFO" | grep -q '7448 4112'; then echo "  ERROR: ese phone_number_id no es el +56 9 7448 4112. No cambié nada."; exit 1; fi
  cp .env .env.bak.$TS
  if grep -q '^META_PHONE_NUMBER_ID=' .env; then sed -i "s|^META_PHONE_NUMBER_ID=.*|META_PHONE_NUMBER_ID=$NUEVO|" .env; else echo "META_PHONE_NUMBER_ID=$NUEVO" >> .env; fi
  # El 4487 9810 vuelve a ser solo de Home Assistant: todo lo que llegue ahí va a HA
  if grep -q '^HA_TELEFONOS=' .env; then sed -i "s|^HA_TELEFONOS=.*|HA_TELEFONOS=|" .env; fi
  php artisan config:clear >/dev/null; php artisan queue:restart >/dev/null
  echo "  Listo: el bot de reservas responde desde el 7448 4112 y el 4487 9810 queda solo para Home Assistant."
  echo "  Si algo sale mal: cp .env.bak.$TS .env && php artisan config:clear"
else
  echo "  Para conectar: abre app.botacura.cl/bot-configuracion/whatsapp-conectar y sigue los pasos con el celular del 7448 4112."
  echo "  Al terminar, corre este mismo comando con el phone_number_id que muestre la página: ... | bash -s -- <phone_number_id>"
fi
