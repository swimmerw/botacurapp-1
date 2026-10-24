#!/bin/bash
# Afiches nuevos de los programas en botacura.cl (valor por persona, lunes a domingo), Desayuno/Once fuera de la tienda,
# "Mínimo 2 personas" y "lunes a domingo" en las fichas.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
R=https://raw.githubusercontent.com/swimmerw/botacurapp-1/${SHA:-parches-finanzas}
echo "Version: ${SHA:0:7}"
sudo mkdir -p public/infografias
for s in wellness-day wellness-plus relax-day full-day botacura-full; do
  sudo curl -fsSL "$R/afiches_web/afiche-$s-noviembre.jpg" -o "public/infografias/afiche-$s-noviembre.jpg" || { echo "ERROR bajando $s"; exit 1; }
done
sudo chown -R www-data:www-data public/infografias 2>/dev/null
F=app/Services/ProgramaContentBuilder.php; cp $F $F.bak.$(date +%Y%m%d%H%M)
curl -fsSL $R/webprod/patch.py -o /tmp/wp1.py && curl -fsSL $R/afiches_web/patch.py -o /tmp/wp2.py && curl -fsSL $R/afiches_web/subir.php -o /tmp/subir.php || exit 1
python3 /tmp/wp1.py "$PWD"; python3 /tmp/wp2.py "$PWD"; php -l $F >/dev/null || { echo "ERROR en la ficha"; exit 1; }
curl -fsSL $R/webprod/datos.php -o /tmp/wp_datos.php && php /tmp/wp_datos.php >/dev/null 2>&1
php /tmp/subir.php; rm -f /tmp/wp1.py /tmp/wp2.py /tmp/subir.php /tmp/wp_datos.php
echo LISTO
