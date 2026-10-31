#!/bin/bash
# Bot-Acura en Instagram (mensajes directos): webhook /api/instagram/webhook y canal "instagram" en el bot.
# Queda APAGADO (IG_BOT_ACTIVO=0) hasta completar los datos de Meta en .env. Se puede correr de nuevo.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/instagram
echo "Version: ${SHA:0:7}"
T=$(date +%Y%m%d%H%M)
C=app/Http/Controllers/Api/BotController.php
FILES="routes/api.php $C"
for f in $FILES; do sudo cp $f $f.bak.$T; done
restaurar(){ for f in $FILES; do sudo cp $f.bak.$T $f; done; echo "ERROR: $1 (se restauraron los respaldos)"; exit 1; }
curl -fsSL $R/InstagramWebhookController.php -o /tmp/igc.php || exit 1
curl -fsSL $R/patch.py -o /tmp/igp.py || exit 1
php -l /tmp/igc.php >/dev/null || { echo "ERROR de sintaxis en el controlador"; exit 1; }
sudo cp /tmp/igc.php app/Http/Controllers/Api/InstagramWebhookController.php
sudo chown www-data:www-data app/Http/Controllers/Api/InstagramWebhookController.php
sudo python3 /tmp/igp.py "$PWD" || restaurar "parche"
for f in $FILES; do php -l $f >/dev/null || restaurar "sintaxis en $f"; done
# Variables de .env (solo se agregan las que faltan, vacías; no se tocan las que ya existen)
sudo cp .env .env.bak.$T
for k in IG_VERIFY_TOKEN IG_ACCESS_TOKEN IG_ACCOUNT_ID IG_SEND_ID IG_APP_ID IG_APP_SECRET; do
  grep -q "^$k=" .env || echo "$k=" | sudo tee -a .env >/dev/null
done
grep -q "^IG_API_BASE=" .env || echo "IG_API_BASE=https://graph.instagram.com" | sudo tee -a .env >/dev/null
grep -q "^IG_API_VERSION=" .env || echo "IG_API_VERSION=v23.0" | sudo tee -a .env >/dev/null
grep -q "^IG_BOT_ACTIVO=" .env || echo "IG_BOT_ACTIVO=0" | sudo tee -a .env >/dev/null
if grep -q "^IG_VERIFY_TOKEN=$" .env; then
  TOK=$(openssl rand -hex 16); sudo sed -i "s/^IG_VERIFY_TOKEN=$/IG_VERIFY_TOKEN=$TOK/" .env
fi
sudo -u www-data php artisan config:clear >/dev/null; sudo -u www-data php artisan route:clear >/dev/null
sudo -u www-data php artisan route:list 2>/dev/null | grep -q instagram.webhook.handle && echo "Ruta OK" || echo "OJO: no veo la ruta"
echo "Token de verificación para Meta (cópialo en el webhook de Instagram): $(grep '^IG_VERIFY_TOKEN=' .env | cut -d= -f2)"
rm -f /tmp/igc.php /tmp/igp.py
echo LISTO
