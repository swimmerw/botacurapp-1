<?php
// Reenvía la última respuesta del bot a los clientes a los que Meta NO se la entregó hoy (p. ej. por el método de pago).
//   listar  -> muestra a quiénes y qué se reenviaría      enviar -> reenvía
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$modo = $argv[1] ?? 'listar';
$tels = [];
foreach (glob(storage_path('logs/laravel-' . date('Y-m-d') . '.log')) as $f) {
    foreach (file($f) as $l) {
        if (strpos($l, 'NO entregado') !== false && preg_match('/"telefono":"(\d+)"/', $l, $m)) { $tels[$m[1]] = true; }
    }
}
$v = env('META_API_VERSION', 'v26.0'); $tok = env('META_WHATSAPP_TOKEN'); $pid = env('META_PHONE_NUMBER_ID');
$http = new GuzzleHttp\Client(['timeout' => 20, 'http_errors' => false]);
$n = 0;
foreach (array_keys($tels) as $t) {
    $c = DB::table('bot_conversaciones')->where('usuario_id', $t)->where('canal', 'whatsapp')->orderBy('id', 'desc')->first();
    $h = $c ? (json_decode($c->historial_json ?: '[]', true) ?: []) : [];
    $u = end($h);
    if (!$u) { echo "+{$t}: sin conversación\n"; continue; }
    if (($u['role'] ?? '') === 'user') { echo "+{$t}: el cliente escribió después → usar retomar_pendientes (conv {$c->id})\n"; continue; }
    $txt = trim(preg_replace('/\n*\[Equipo Botacura respondió\][\s\S]*$/u', '', (string) $u['content']));
    if ($txt === '') { echo "+{$t}: lo último fue del equipo, no se reenvía\n"; continue; }
    if ($modo !== 'enviar') { echo "+{$t}: " . mb_substr(str_replace("\n", ' ', $txt), 0, 160) . "\n"; continue; }
    $r = $http->post("https://graph.facebook.com/{$v}/{$pid}/messages", ['headers' => ['Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json'],
        'json' => ['messaging_product' => 'whatsapp', 'to' => $t, 'type' => 'text', 'text' => ['body' => $txt]]]);
    echo ($r->getStatusCode() < 300 ? 'REENVIADO' : 'ERROR ' . $r->getStatusCode()) . " +{$t}\n"; $n++;
    sleep(1);
}
echo count($tels) . " clientes con mensajes no entregados hoy" . ($modo === 'enviar' ? ", {$n} reenviados (revisa en unos minutos que no vuelvan a fallar)" : ". Para reenviar: ... | bash -s -- enviar") . "\n";
