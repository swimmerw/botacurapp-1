#!/bin/bash
# Gift Card: nuevo diseno "Valle" (A4 apaisado, logo oficial, foto de Botacura),
# vista previa del PDF (boton "Ver PDF") y arreglo del error 500 al Enviar.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/giftcard
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
F="resources/views/pdf/giftcard/viewPDF.blade.php app/Mail/GiftCardMailable.php public/images/gc/giftcard-foto.jpg public/images/gc/logo-crema.png public/assents/fonts/giftcard/fraunces-400.ttf public/assents/fonts/giftcard/fraunces-600.ttf public/assents/fonts/giftcard/manrope-400.ttf public/assents/fonts/giftcard/manrope-500.ttf public/assents/fonts/giftcard/manrope-700.ttf public/assents/fonts/giftcard/OFL-Fraunces.txt public/assents/fonts/giftcard/OFL-Manrope.txt"
for f in $F; do
  mkdir -p "$(dirname "$f")"
  curl -fsSL "$U/$f" -o "$f.nuevo" || { echo "ERROR bajando $f"; exit 1; }
  if [[ "$f" == *.php && "$f" != *.blade.php ]]; then php -l "$f.nuevo" >/dev/null || { echo "ERROR de sintaxis en $f"; rm -f "$f.nuevo"; exit 1; }; fi
  if [ -f "$f" ] && cmp -s "$f" "$f.nuevo"; then rm -f "$f.nuevo"; continue; fi
  [ -f "$f" ] && cp "$f" "$f.bak.$TS"
  mv "$f.nuevo" "$f"
done
echo "Archivos OK"

C=app/Http/Controllers/GiftCardController.php
cp $C $C.bak.$TS
curl -fsSL "$U/patch_ctrl.py" | python3 - $C || { echo "ERROR en el controlador"; cp $C.bak.$TS $C; exit 1; }
php -l $C >/dev/null || { echo "ERROR de sintaxis en el controlador, se restaura"; cp $C.bak.$TS $C; exit 1; }

python3 - <<'PY' || { echo "ERROR en rutas o vista"; exit 1; }
p='routes/web.php'; s=open(p).read()
k="Route::get('giftcards/{gc}/enviarpdf', 'GiftCardController@enviarpdf')->name('giftcards.enviar');"
if 'giftcards.verpdf' not in s:
    if k not in s: raise SystemExit('no encontre la ruta giftcards.enviar')
    s=s.replace(k, k+"\n    Route::get('giftcards/{gc}/pdf', 'GiftCardController@verpdf')->name('giftcards.verpdf');",1)
    open(p,'w').write(s)
p='resources/views/themes/backoffice/pages/giftcard/show.blade.php'; s=open(p).read()
k='<a class="btn blue" href="{{route(\'backoffice.giftcards.enviar\',$gc)}}">'
if 'giftcards.verpdf' not in s and k in s:
    s=s.replace(k,'<a class="btn grey darken-1" target="_blank" href="{{route(\'backoffice.giftcards.verpdf\',$gc)}}">Ver PDF<i class=\'material-icons right\'>picture_as_pdf</i></a>\n                                    '+k,1)
    open(p,'w').write(s)
print('Ruta y boton "Ver PDF" OK')
PY
php artisan route:clear >/dev/null; php artisan view:clear >/dev/null
echo "----"
echo "Listo. Revisa una gift card con el boton 'Ver PDF' antes de enviarla."
