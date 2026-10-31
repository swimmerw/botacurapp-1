#!/bin/bash
# Guarda el token de Instagram en .env sin mostrarlo, detecta el tipo de token y prueba la conexión.
# Uso: curl -fsSL .../ig_configurar.sh -o /tmp/igc.sh && bash /tmp/igc.sh
cd /var/www/html/botacurapp || exit 1
IG_ID=17841409198372997
APP_ID=1429363716050417
read -s -p "Pega el token de Instagram (no se mostrará) y presiona Enter: " TOK; echo
[ -n "$TOK" ] || { echo "No pegaste nada"; exit 1; }
case "$TOK" in
  IG*) BASE=https://graph.instagram.com ;;
  *)   BASE=https://graph.facebook.com ;;
esac
sudo cp .env .env.bak.$(date +%Y%m%d%H%M)
poner(){ if grep -q "^$1=" .env; then sudo sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" | sudo tee -a .env >/dev/null; fi; }
poner IG_ACCESS_TOKEN "$TOK"
poner IG_ACCOUNT_ID "$IG_ID"
poner IG_SEND_ID "$IG_ID"
poner IG_APP_ID "$APP_ID"
poner IG_API_BASE "$BASE"
grep -q "^IG_BOT_ACTIVO=" .env || poner IG_BOT_ACTIVO 0
sudo -u www-data php artisan config:clear >/dev/null
echo "Probando la conexión con $BASE ..."
R=$(curl -s "$BASE/v23.0/$IG_ID?fields=username,name" -H "Authorization: Bearer $TOK")
unset TOK
echo "$R" | grep -q '"username"' && echo "OK: $(echo "$R" | grep -o '"username":"[^"]*"')" || echo "OJO, Meta respondió: $(echo "$R" | cut -c1-300)"
echo "Bot de Instagram: $(grep '^IG_BOT_ACTIVO=' .env | cut -d= -f2) (0 = apagado)"
echo "Token de verificación para el webhook: $(grep '^IG_VERIFY_TOKEN=' .env | cut -d= -f2)"
echo LISTO
