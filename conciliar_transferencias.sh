#!/bin/bash
# Solo lectura: cruza comprobantes de WhatsApp con reservas y abonos. Uso: ... | bash -s -- 14   (dias hacia atras)
cd /var/www/html/botacurapp || exit 1
curl -fsSL https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas/bot/conciliar_transferencias.php -o /tmp/conciliar.php || exit 1
sudo -u www-data php /tmp/conciliar.php "${1:-14}"; rm -f /tmp/conciliar.php
