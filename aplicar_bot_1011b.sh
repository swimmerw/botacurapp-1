#!/bin/bash
# 10/11/2026 (b). Uso: ... | bash   (o bash -s -- 3 para dejar la pausa en 3 horas)
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/ajustes_1011b.php -o /tmp/aj.php || exit 1
OUT=$(sudo -u www-data php /tmp/aj.php "${1:-2}"); echo "$OUT"; rm -f /tmp/aj.php
M=$(echo "$OUT" | grep MODELO_OK | awk '{print $2}')
if [ -n "$M" ]; then
  sudo cp .env .env.bak.$(date +%Y%m%d%H%M%S)
  if grep -q '^ANTHROPIC_MODEL=' .env; then sudo sed -i "s/^ANTHROPIC_MODEL=.*/ANTHROPIC_MODEL=$M/" .env; else echo "ANTHROPIC_MODEL=$M" | sudo tee -a .env >/dev/null; fi
  php artisan config:clear >/dev/null 2>&1
  echo "Bot usando ahora: $(grep '^ANTHROPIC_MODEL=' .env | cut -d= -f2)"
  echo "Para volver al anterior: sudo sed -i 's/^ANTHROPIC_MODEL=.*/ANTHROPIC_MODEL=claude-haiku-4-5-20251001/' .env && php artisan config:clear"
fi
echo "LISTO"
