#!/bin/bash
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/patch_modelo_1011.py -o /tmp/pm.py || exit 1
OUT=$(sudo python3 /tmp/pm.py); echo "$OUT"; rm -f /tmp/pm.py
ST=$(echo "$OUT" | grep PARCHE_OK | awk '{print $2}')
for f in BotController WhatsAppWebhookController; do
  if ! php -l app/Http/Controllers/Api/$f.php >/dev/null; then
    [ -n "$ST" ] && for g in BotController WhatsAppWebhookController; do sudo cp app/Http/Controllers/Api/$g.php.bak.$ST app/Http/Controllers/Api/$g.php; done
    echo "ERROR de sintaxis: restaurado"; exit 1
  fi
done
echo "Sintaxis OK · modelo: $(grep '^ANTHROPIC_MODEL=' .env | cut -d= -f2)"
echo "LISTO"
