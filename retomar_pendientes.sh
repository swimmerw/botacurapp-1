#!/bin/bash
# Uso:  ... | bash                -> lista los chats sin respuesta
#       ... | bash -s -- 12,34,56 -> el bot responde esos chats y se envía por WhatsApp
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/retomar_pendientes.php -o /tmp/retomar_pendientes.php || exit 1
if [ -n "$1" ]; then sudo -u www-data php /tmp/retomar_pendientes.php enviar "$1"; else sudo -u www-data php /tmp/retomar_pendientes.php listar; fi
rm -f /tmp/retomar_pendientes.php
