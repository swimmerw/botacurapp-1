#!/bin/bash
# Solo lectura: revisa la conexión botacura.cl (WooCommerce) → BotacurApp.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}/web/diag.php" -o /tmp/diag_web.php && php /tmp/diag_web.php; rm -f /tmp/diag_web.php
grep -c "WC-Webhook" storage/logs/laravel*.log 2>/dev/null | tail -3 | sed 's/^/Líneas WC-Webhook en logs: /'
grep -h "WC-Webhook" storage/logs/laravel*.log 2>/dev/null | tail -8 | cut -c1-220
