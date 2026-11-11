#!/bin/bash
# Marca como atendidas todas las alertas pendientes de la app. Para deshacer: ... | bash -s -- deshacer
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/limpiar_alertas.php -o /tmp/la.php || exit 1
sudo -u www-data php /tmp/la.php "$@" 2>&1 | grep -v "^PHP Deprecated"; rm -f /tmp/la.php; echo LISTO
