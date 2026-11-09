<?php
// Retomar conversaciones de WhatsApp que quedaron sin respuesta (09/11/2026).
//   listar             -> muestra los chats candidatos (no envía nada)
//   enviar ID,ID,...   -> el bot responde esos chats con todo el contexto y se envía por WhatsApp
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$modo = $argv[1] ?? 'listar';
$ids  = isset($argv[2]) ? array_filter(array_map('intval', explode(',', $argv[2]))) : [];
$horas = (int) \App\Services\BotTarifasService::horasSilencioHumano();
$equipo = ['961910398'];
foreach (DB::table('equipo_config')->pluck('telefono') as $t) {
    $d = substr(preg_replace('/\D/', '', (string) $t), -9);
    if ($d) { $equipo[] = $d; }
}

$q = DB::table('bot_conversaciones')->where('canal', 'whatsapp')->where('activo', 1)
    ->where('updated_at', '>=', max(now()->subHours(23)->toDateTimeString(), '2026-11-09 09:40:00'));
if ($modo === 'enviar') {
    $q->whereIn('id', $ids);
}
$cands = [];
foreach ($q->orderBy('updated_at')->get() as $c) {
    $h = json_decode($c->historial_json ?: '[]', true) ?: [];
    $u = end($h);
    if (!$u || ($u['role'] ?? '') !== 'user') { continue; }
    if (in_array(substr($c->usuario_id, -9), $equipo, true)) { continue; }
    $equipoAt = ($c->requiere_humano && $c->humano_hasta) ? \Carbon\Carbon::parse($c->humano_hasta)->subHours($horas) : null;
    $c->equipo_hace = $equipoAt ? $equipoAt->diffInMinutes(now()) : null;
    $c->espera = \Carbon\Carbon::parse($c->updated_at)->diffInMinutes(now());
    $c->ultimo = trim(preg_replace('/\s+/', ' ', str_replace('[Cliente, mientras lo atendía el equipo]:', '»', (string) $u['content'])));
    $cands[] = $c;
}

if ($modo !== 'enviar') {
    printf("%-5s %-12s %-6s %-14s %s\n", 'ID', 'TELEFONO', 'ESPERA', 'EQUIPO', 'ULTIMO MENSAJE DEL CLIENTE');
    foreach ($cands as $c) {
        printf("%-5d %-12s %-6s %-14s %s\n", $c->id, $c->usuario_id, $c->espera . 'm',
            $c->equipo_hace === null ? '-' : 'respondió ' . $c->equipo_hace . 'm',
            mb_substr(preg_replace('/\[Sistema[^\]]*\]/u', '', $c->ultimo), -170));
    }
    echo count($cands) . " chats sin respuesta. Para que el bot los retome: ... enviar ID,ID,ID\n";
    exit(0);
}

$phoneId = env('META_PHONE_NUMBER_ID'); $token = env('META_WHATSAPP_TOKEN'); $ver = env('META_API_VERSION', 'v26.0');
$http = new GuzzleHttp\Client(['timeout' => 40, 'http_errors' => false]);
foreach ($cands as $c) {
    // Devolver el chat al bot (sigue marcado como atendido por el equipo para que el bot lea lo que se habló)
    DB::table('bot_conversaciones')->where('id', $c->id)->update(['humano_hasta' => null, 'updated_at' => now()]);
    if (\Illuminate\Support\Facades\Schema::hasTable('bot_chats_pausados')) {
        DB::table('bot_chats_pausados')->where('telefono', $c->usuario_id)->delete();
    }
    $r = $http->post(url('/api/bot-ai/message'), [
        'headers' => ['X-Bot-Secret' => config('services.bot.secret'), 'X-Bot-Token' => env('BOT_API_TOKEN'), 'Content-Type' => 'application/json'],
        'json' => ['telefono' => $c->usuario_id, 'nombre' => $c->nombre_cliente ?: 'Cliente',
            'mensaje' => '[Sistema-retomar: el cliente escribió y nadie le respondió. Discúlpate en una línea por la demora y responde lo que necesitaba, siguiendo desde el último punto de la conversación.]'],
    ]);
    $j = json_decode((string) $r->getBody(), true) ?: [];
    $msg = trim((string) ($j['mensaje'] ?? ''));
    if ($msg === '') { echo "#{$c->id} {$c->usuario_id}: el bot no generó respuesta (HTTP {$r->getStatusCode()})\n"; continue; }
    $s = $http->post("https://graph.facebook.com/{$ver}/{$phoneId}/messages", [
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'json' => ['messaging_product' => 'whatsapp', 'to' => $c->usuario_id, 'type' => 'text', 'text' => ['body' => $msg]],
    ]);
    $ok = $s->getStatusCode() < 300;
    if ($ok) {
        \App\BotMensajePendiente::where('telefono', 'like', '%' . substr($c->usuario_id, -9))->where('estado', 'pendiente')
            ->update(['estado' => 'atendido', 'updated_at' => now()]);
    }
    \Log::info('[Bot] Retomar pendiente', ['conv' => $c->id, 'telefono' => $c->usuario_id, 'enviado' => $ok]);
    echo ($ok ? 'ENVIADO' : 'ERROR ENVÍO ' . $s->getStatusCode()) . " #{$c->id} {$c->usuario_id}: " . mb_substr(str_replace("\n", ' ', $msg), 0, 220) . "\n";
    sleep(1);
}
