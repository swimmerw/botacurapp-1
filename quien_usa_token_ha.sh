#!/bin/bash
# Muestra que tokens de Home Assistant aparecen en archivos de este equipo, SIN mostrar el token:
# solo el archivo y el "iss" (id del token en HA) para compararlo con GAS / Claude / Whatsapp / IOT. 15/10/2026
D=${1:-$HOME}
sudo grep -rIloE "eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}" $D /etc/botacura* 2>/dev/null \
 | grep -vE "/node_modules/|/\.git/|/vendor/|\.log$|/storage/framework/" | while read f; do
  sudo grep -hoE "eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}" "$f" | sort -u | while read t; do
    iss=$(python3 -c "import sys,json,base64;p=sys.argv[1].split('.')[1];p+='='*(-len(p)%4);print(json.loads(base64.urlsafe_b64decode(p)).get('iss','-'))" "$t" 2>/dev/null)
    echo "$iss  $f"
  done
done | sort -u
echo "--- ids en HA: GAS=e501b50c..., Claude=ce741205..., Whatsapp=a1d52307..., IOT=ef63530b..."
