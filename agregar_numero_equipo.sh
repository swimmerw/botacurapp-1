#!/bin/bash
# Agrega 56961249012 a los numeros del equipo que pueden usar #reiniciar
cd /var/www/html/botacurapp || exit 1
N=56961249012
cp .env .env.bak.$(date +%Y%m%d%H%M%S)
if grep -q '^BOT_TELEFONOS_EQUIPO=' .env; then
  grep -q "^BOT_TELEFONOS_EQUIPO=.*$N" .env || sed -i "s/^BOT_TELEFONOS_EQUIPO=\(.*\)$/BOT_TELEFONOS_EQUIPO=\1,$N/" .env
else
  echo "BOT_TELEFONOS_EQUIPO=56961910398,56940014282,$N" >> .env
fi
php artisan config:clear >/dev/null
grep '^BOT_TELEFONOS_EQUIPO=' .env
echo LISTO
