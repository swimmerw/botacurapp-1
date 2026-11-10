<?php
// Aclara a los clientes a los que el bot les dijo que el sábado 14/11 "no estaba habilitado" (o similar):
// ese día está cerrado porque no quedan cupos. (10/11/2026)
//   listar -> muestra a quién y qué se enviaría      enviar -> envía y lo deja en el historial como mensaje del equipo
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$modo = $argv[1] ?? 'listar';
$v = env('META_API_VERSION', 'v26.0'); $tok = env('META_WHATSAPP_TOKEN'); $pid = env('META_PHONE_NUMBER_ID');
$http = new GuzzleHttp\Client(['timeout' => 20, 'http_errors' => false]);

$convs = DB::table('bot_conversaciones')->where('canal', 'whatsapp')
    ->where('updated_at', '>=', now()->subDays(7))->orderBy('updated_at', 'desc')->get();
$n = 0; $enviados = 0;
foreach ($convs as $c) {
    $h = json_decode($c->historial_json ?: '[]', true) ?: [];
    $idx = null;
    foreach ($h as $i => $m) {
        if (($m['role'] ?? '') !== 'assistant') { continue; }
        $t = mb_strtolower((string) ($m['content'] ?? ''));
        if (preg_match('/(s[aá]bado\s+)?14 de noviembre|s[aá]bado 14\b/u', $t)
            && preg_match('/no est[aá] habilitad|a[uú]n no|no tenemos disponibilidad|no hay disponibilidad|sin disponibilidad|no tenemos cupo|no hay cupo|no est[aá] disponible/u', $t)) {
            $idx = $i;
        }
    }
    if ($idx === null) { continue; }
    $ya = false; $otra = null;
    foreach (array_slice($h, $idx + 1) as $m) {
        $t = (string) ($m['content'] ?? '');
        if (strpos($t, 'cerrado porque no quedan cupos') !== false) { $ya = true; }
        if (($m['role'] ?? '') === 'assistant' && preg_match('/disponibilidad para el ((lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo) \d{1,2} de [a-z]+)/iu', $t, $mm)) {
            $otra = $mm[1];
        }
    }
    if ($ya) { echo "+{$c->usuario_id}: ya avisado\n"; continue; }
    $n++;
    $nombre = trim((string) ($c->nombre_cliente ?? ''));
    $pila = $nombre && stripos($nombre, 'cliente') === false ? explode(' ', trim(preg_replace('/[^A-Za-zÁÉÍÓÚÑáéíóúñü ]+/u', ' ', $nombre)))[0] : '';
    if (mb_strlen($pila) < 2) { $pila = ''; }
    $txt = 'Hola' . ($pila ? ' ' . $pila : '') . ', una aclaración sobre el sábado 14 de noviembre: ese día ya está cerrado porque no quedan cupos. Te pedimos disculpas por la confusión. '
        . ($otra ? 'Seguimos con el ' . $otra . ' cuando nos confirmes 😊' : 'Si quieres te revisamos otra fecha 😊');
    $ultimoCliente = Carbon\Carbon::parse($c->updated_at);
    echo "+{$c->usuario_id} ({$nombre}, último movimiento " . $ultimoCliente->diffForHumans() . "):\n   {$txt}\n";
    if ($modo !== 'enviar') { continue; }
    $r = $http->post("https://graph.facebook.com/{$v}/{$pid}/messages", ['headers' => ['Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json'],
        'json' => ['messaging_product' => 'whatsapp', 'to' => $c->usuario_id, 'type' => 'text', 'text' => ['body' => $txt]]]);
    $ok = $r->getStatusCode() < 300;
    echo '   ' . ($ok ? 'ENVIADO' : 'ERROR ' . $r->getStatusCode() . ' ' . mb_substr((string) $r->getBody(), 0, 200)) . "\n";
    if ($ok) {
        $enviados++;
        $nota = '[Equipo Botacura respondió] ' . $txt;
        if ($h && (end($h)['role'] ?? '') === 'assistant') { $h[count($h) - 1]['content'] .= "\n\n" . $nota; } else { $h[] = ['role' => 'assistant', 'content' => $nota]; }
        DB::table('bot_conversaciones')->where('id', $c->id)->update(['historial_json' => json_encode($h, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }
    sleep(1);
}
echo "{$n} clientes a avisar" . ($modo === 'enviar' ? ", {$enviados} enviados" : '. Para enviar: ... | bash -s -- enviar') . "\n";
