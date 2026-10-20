#!/bin/bash
# Bot-Acura: audios (transcripción con Groq u OpenAI) + revisión de lo que falta para la coexistencia del 7448 4112.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/bot
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
for f in app/Services/WhisperService.php app/Console/Commands/BotProbarAudio.php; do
  curl -fsSL "$U/$f" -o "$f.nuevo" || { echo "ERROR bajando $f"; exit 1; }
  php -l "$f.nuevo" >/dev/null || { echo "ERROR de sintaxis en $f"; rm -f "$f.nuevo"; exit 1; }
  [ -f "$f" ] && cp "$f" "$f.bak.$TS"
  mv "$f.nuevo" "$f"
done
S=config/services.php
if ! grep -q "'groq'" $S; then
  cp $S $S.bak.$TS
  python3 - <<'PY' || { cp $S.bak.$TS $S; echo "ERROR en config/services.php"; exit 1; }
p='config/services.php'; s=open(p).read()
add = """
    // Groq (Whisper rápido con capa gratuita) y elección del proveedor de transcripción
    'groq' => [
        'key'          => env('GROQ_API_KEY'),
        'modelo_audio' => env('GROQ_MODELO_AUDIO', 'whisper-large-v3-turbo'),
    ],
    'transcripcion' => [
        'proveedor' => env('TRANSCRIPCION_PROVEEDOR'),
    ],
"""
i = s.rfind('];')
s = s[:i] + add + s[i:]
open(p,'w').write(s)
PY
  php -l $S >/dev/null || { cp $S.bak.$TS $S; echo "ERROR de sintaxis en services.php, restaurado"; exit 1; }
fi
grep -q '^GROQ_API_KEY=' .env || printf '\n# Transcripción de audios del bot (Groq: console.groq.com > API Keys)\nGROQ_API_KEY=\n' >> .env
php artisan config:clear >/dev/null
echo "Audios: instalado"
echo "----"
echo "Estado (sin mostrar claves):"
for k in GROQ_API_KEY OPENAI_API_KEY ANTHROPIC_API_KEY META_APP_ID META_APP_SECRET META_ES_CONFIG_ID META_WHATSAPP_TOKEN META_PHONE_NUMBER_ID META_VERIFY_TOKEN HA_PHONE_NUMBER_ID; do
  v=$(grep -m1 "^$k=" .env | cut -d= -f2- | tr -d '"')
  if [ -z "$v" ]; then echo "  $k: (vacío)"; elif [[ "$k" == *_ID ]]; then echo "  $k: $v"; else echo "  $k: ok (${#v} caracteres)"; fi
done
php artisan route:list 2>/dev/null | grep -E "whatsapp-conectar|webhook" | awk '{print "  ruta:", $0}' | cut -c1-140
php artisan tinker --execute="
\$h=new GuzzleHttp\Client(['timeout'=>20,'http_errors'=>false]); \$g='https://graph.facebook.com/v25.0/';
\$app=env('META_APP_ID'); \$sec=env('META_APP_SECRET'); \$tok=env('META_WHATSAPP_TOKEN');
if(\$app && \$sec){ \$r=json_decode((string)\$h->get(\$g.\$app.'/subscriptions',['query'=>['access_token'=>\$app.'|'.\$sec]])->getBody(),true);
  foreach((\$r['data']??[]) as \$s){ echo '  webhook '.\$s['object'].': '.(\$s['callback_url']??'').' campos: '.implode(',',array_map(function(\$f){return \$f['name'];},\$s['fields']??[])).PHP_EOL; }
  if(isset(\$r['error'])) echo '  webhook: '.\$r['error']['message'].PHP_EOL;
  if(\$tok){ \$d=json_decode((string)\$h->get(\$g.'debug_token',['query'=>['input_token'=>\$tok,'access_token'=>\$app.'|'.\$sec]])->getBody(),true)['data']??[];
    echo '  token: '.(!empty(\$d['is_valid'])?'válido':'NO válido').' · vence: '.(empty(\$d['expires_at'])?'nunca':date('d-m-Y',\$d['expires_at'])).' · permisos: '.implode(',',\$d['scopes']??[]).PHP_EOL; }
} else { echo '  (sin META_APP_ID/META_APP_SECRET no puedo revisar el webhook ni el token)'.PHP_EOL; }
if(\$tok && env('META_PHONE_NUMBER_ID')){ \$p=json_decode((string)\$h->get(\$g.env('META_PHONE_NUMBER_ID'),['headers'=>['Authorization'=>'Bearer '.\$tok],'query'=>['fields'=>'display_phone_number,verified_name,is_on_biz_app,platform_type,quality_rating']])->getBody(),true);
  echo '  número del bot: '.json_encode(\$p,JSON_UNESCAPED_UNICODE).PHP_EOL; }
" 2>/dev/null
