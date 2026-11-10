<?php
// Rescatar chats cuyas respuestas del bot probablemente no llegaron (bloqueo de pago de Meta, 10/11/2026).
//   php rescatar.php listar [desde HH:MM] [excluir ids]   |   php rescatar.php enviar [desde HH:MM] [excluir ids]
// Si la última respuesta fue "tuve un problema", el bot genera una nueva con todo el contexto; si no, se reenvía la última.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$modo = $argv[1] ?? 'listar';
$desde = date('Y-m-d') . ' ' . ($argv[2] ?? '09:00') . ':00';
$hasta = date('Y-m-d') . ' 13:22:00';  // desde aquí Meta ya entrega
$excluir = isset($argv[3]) ? array_map('intval', explode(',', $argv[3])) : [];
$equipo = ['961910398', '940014282'];
foreach (DB::table('equipo_config')->pluck('telefono') as $t) { $equipo[] = substr(preg_replace('/\D/', '', (string) $t), -9); }
$cierre = '/de nada|que est[eé]s bien|cuando (decidas|quieras)|nos vemos|sin apuro|cualquier (cosa|duda)|que disfrutes/iu';
$v = env('META_API_VERSION', 'v26.0'); $tok = env('META_WHATSAPP_TOKEN'); $pid = env('META_PHONE_NUMBER_ID');
$http = new GuzzleHttp\Client(['timeout' => 45, 'http_errors' => false]);
$q = DB::table('bot_conversaciones')->where('canal', 'whatsapp')->where('activo', 1)
    ->where('updated_at', '>=', max($desde, now()->subHours(23)->toDateTimeString()))->where('updated_at', '<', $hasta)->orderBy('updated_at');
$n = 0; $env = 0;
foreach ($q->get() as $c) {
    if (in_array((int) $c->id, $excluir, true) || in_array(substr($c->usuario_id, -9), $equipo, true)) { continue; }
    if ($c->humano_hasta && \Carbon\Carbon::parse($c->humano_hasta)->gt(now())) { continue; }   // lo está viendo el equipo
    $h = json_decode($c->historial_json ?: '[]', true) ?: [];
    $u = end($h);
    if (!$u || $u['role'] !== 'assistant' || strpos((string) $u['content'], '[Equipo Botacura respondió]') !== false) { continue; }
    $txt = trim((string) $u['content']);
    $regenerar = mb_stripos($txt, 'tuve un problema procesando') !== false;
    if (!$regenerar && preg_match($cierre, $txt) && mb_strlen($txt) < 160) { continue; }
    $n++;
    $accion = $regenerar ? 'NUEVA RESPUESTA' : 'REENVIAR';
    if ($modo !== 'enviar') {
        printf("#%-5d +%s %-16s %s (%s): %s\n", $c->id, $c->usuario_id, mb_substr((string) $c->nombre_cliente, 0, 16), $accion,
            substr($c->updated_at, 11, 5), mb_substr(str_replace("\n", ' ', $txt), 0, 120));
        continue;
    }
    if ($regenerar) {
        $r = $http->post(url('/api/bot-ai/message'), ['headers' => ['X-Bot-Secret' => config('services.bot.secret'), 'X-Bot-Token' => env('BOT_API_TOKEN'), 'Content-Type' => 'application/json'],
            'json' => ['telefono' => $c->usuario_id, 'nombre' => $c->nombre_cliente ?: 'Cliente',
                'mensaje' => '[Sistema-retomar: tu respuesta anterior no le llegó al cliente por una falla técnica. Discúlpate en una línea por la demora y responde lo que preguntó en su último mensaje, con todo el contexto.]']]);
        $txt = trim((string) ((json_decode((string) $r->getBody(), true) ?: [])['mensaje'] ?? ''));
        if ($txt === '') { echo "#{$c->id}: el bot no generó respuesta\n"; continue; }
    } else {
        $txt = "Disculpa, este mensaje no te había llegado por una falla técnica 🙏\n\n" . $txt;
    }
    $s = $http->post("https://graph.facebook.com/{$v}/{$pid}/messages", ['headers' => ['Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json'],
        'json' => ['messaging_product' => 'whatsapp', 'to' => $c->usuario_id, 'type' => 'text', 'text' => ['body' => $txt]]]);
    $ok = $s->getStatusCode() < 300; $env += $ok ? 1 : 0;
    \Log::info('[Bot] Rescate', ['conv' => $c->id, 'telefono' => $c->usuario_id, 'accion' => $accion, 'ok' => $ok]);
    echo ($ok ? 'ENVIADO' : 'ERROR ' . $s->getStatusCode()) . " #{$c->id} +{$c->usuario_id} {$accion}: " . mb_substr(str_replace("\n", ' ', $txt), 0, 160) . "\n";
    sleep(1);
}
echo "{$n} chats" . ($modo === 'enviar' ? ", {$env} enviados. En 2 minutos revisa: grep 'NO entregado' del log." : ". Para enviar: ... | bash -s -- enviar " . substr($desde, 11, 5) . " [ids a excluir separados por coma]") . "\n";
