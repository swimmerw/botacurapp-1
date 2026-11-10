<?php
// Corrige a los clientes a los que el bot les ofreció o confirmó el sábado 21/11: ese día ya no tiene cupos. (10/11/2026)
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
        if (preg_match('/21 de noviembre|s[aá]bado 21\b/u', $t)
            && preg_match('/disponib|cupo|te viene bien|les acomoda|tenemos|reserv/u', $t)
            && !preg_match('/21[^.\n]{0,60}(no hay|no tenemos|sin cupo|no queda|no est[aá] habilitad)|(no hay|no tenemos|sin cupo|no queda)[^.\n]{0,60}21/u', $t)) {
            $idx = $i;
        }
    }
    if ($idx === null) { continue; }
    $ya = false; $otra = null;
    foreach (array_slice($h, $idx + 1) as $m) {
        $t = (string) ($m['content'] ?? '');
        if (strpos($t, 'el sábado 21 de noviembre también está completo') !== false) { $ya = true; }
        if (($m['role'] ?? '') === 'assistant' && preg_match('/disponibilidad para el ((lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo) \d{1,2} de [a-z]+)/iu', $t, $mm)) {
            $otra = $mm[1];
        }
    }
    if ($ya) { echo "+{$c->usuario_id}: ya avisado\n"; continue; }
    $n++;
    $nombre = trim((string) ($c->nombre_cliente ?? ''));
    $pila = $nombre && stripos($nombre, 'cliente') === false ? explode(' ', trim(preg_replace('/[^A-Za-zÁÉÍÓÚÑáéíóúñü ]+/u', ' ', $nombre)))[0] : '';
    if (mb_strlen($pila) < 2) { $pila = ''; }
    $txt = 'Hola' . ($pila ? ' ' . $pila : '') . ', te escribimos para corregir: el sábado 21 de noviembre también está completo, ya no quedan cupos. Disculpa la confusión. ¿Qué otra fecha te acomoda? La revisamos y te confirmamos 😊';
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
