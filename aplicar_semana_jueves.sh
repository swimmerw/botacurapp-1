#!/bin/bash
# Programas S: al cliente "solo jueves no festivos"
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/semana_solo_jueves.php -o /tmp/ssj.php || exit 1
php /tmp/ssj.php 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/ssj.php
php -l app/Services/BotPromptService.php >/dev/null || { cp "$(ls -t app/Services/BotPromptService.php.bak.* | head -1)" app/Services/BotPromptService.php; echo "ERROR de sintaxis: restaurado"; exit 1; }
php artisan cache:clear >/dev/null; echo LISTO
