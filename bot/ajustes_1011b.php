<?php
// 10/11/2026 (b): mensajes automaticos de la app que no cuentan como equipo, silencio de 2 h,
// liberar chats pausados por esos mensajes y probar un modelo mas capaz para el bot.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
function guardarTexto(string $clave, string $contenido) {
    if (DB::table('bot_secciones_texto')->where('clave', $clave)->exists()) {
        DB::table('bot_secciones_texto')->where('clave', $clave)->update(['contenido' => $contenido]);
        return;
    }
    // Nueva fila con la misma forma que las existentes (categoria y demas campos obligatorios)
    $modelo = (array) (DB::table('bot_secciones_texto')->where('clave', 'like', 'param_%')->first() ?: DB::table('bot_secciones_texto')->first());
    unset($modelo['id']);
    $modelo['clave'] = $clave; $modelo['contenido'] = $contenido;
    foreach (['titulo', 'nombre', 'descripcion', 'etiqueta'] as $k) { if (array_key_exists($k, $modelo)) { $modelo[$k] = $clave; } }
    foreach (['created_at', 'updated_at'] as $k) { if (array_key_exists($k, $modelo)) { $modelo[$k] = now(); } }
    DB::table('bot_secciones_texto')->insert($modelo);
}

$frases = "en este momento no podemos responder\nfeliz la responderemos\nbienvenid@ a botacura\nsi deseas reservar, indícanos por favor fecha";
guardarTexto('ecos_automaticos', $frases);
$horas = (int) ($argv[1] ?? 2);
guardarTexto('param_horas_silencio_humano', (string) $horas);
echo "Mensajes automáticos de la app (bienvenida y ausencia): ya no pausan al bot\n";
echo "Pausa después de que responde el equipo: {$horas} h\n";

$lista = array_filter(array_map('trim', explode("\n", mb_strtolower($frases))));
$n = 0;
foreach (DB::table('bot_conversaciones')->where('requiere_humano', 1)->whereNotNull('humano_hasta')->where('humano_hasta', '>', now())->get() as $c) {
    $ultimo = null; $momento = null;
    foreach (json_decode($c->historial_json ?: '[]', true) ?: [] as $t) {
        foreach (preg_split('/(?=\[Equipo Botacura respondió\])/u', (string) $t['content']) as $l) {
            if (strpos($l, '[Equipo Botacura respondió]') === 0) { $ultimo = mb_strtolower($l); }
        }
    }
    $auto = false;
    foreach ($lista as $f) { if ($ultimo && mb_strpos($ultimo, $f) !== false) { $auto = true; } }
    $nuevoHasta = \Carbon\Carbon::parse($c->humano_hasta)->subHours(12)->addHours($horas);
    if ($auto) {
        DB::table('bot_conversaciones')->where('id', $c->id)->update(['humano_hasta' => null]); $n++;
    } elseif ($nuevoHasta->lt(\Carbon\Carbon::parse($c->humano_hasta))) {
        DB::table('bot_conversaciones')->where('id', $c->id)->update(['humano_hasta' => $nuevoHasta->lte(now()) ? null : $nuevoHasta]);
    }
}
echo "Chats liberados (los había pausado un mensaje automático): {$n}\n";
echo "Chats en pausa porque el equipo respondió hace menos de {$horas} h: " . DB::table('bot_conversaciones')->where('requiere_humano', 1)->where('humano_hasta', '>', now())->count() . "\n";

// Modelo: se prueba antes de cambiarlo; si no responde bien, queda el actual.
$actual = config('services.anthropic.model');
foreach (['claude-sonnet-5-5', 'claude-sonnet-4-5'] as $m) {
    $r = (new GuzzleHttp\Client(['timeout' => 30, 'http_errors' => false]))->post('https://api.anthropic.com/v1/messages', [
        'headers' => ['x-api-key' => config('services.anthropic.key'), 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json'],
        'json' => ['model' => $m, 'max_tokens' => 20, 'messages' => [['role' => 'user', 'content' => 'Responde solo {"ok":true}']]],
    ]);
    if ($r->getStatusCode() === 200) { echo "MODELO_OK {$m} (antes: {$actual})\n"; exit(0); }
    echo "Modelo {$m} no disponible (HTTP {$r->getStatusCode()})\n";
}
echo "Se mantiene el modelo {$actual}\n";
