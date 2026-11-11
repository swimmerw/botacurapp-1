#!/bin/bash
# Apaga los correos de alerta del bot (quedan solo en la app). Para volver a activarlos: ... | bash -s -- restaurar
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/apagar_correos_alerta.php -o /tmp/aca.php || exit 1
php /tmp/aca.php "$@" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/aca.php; echo LISTO
