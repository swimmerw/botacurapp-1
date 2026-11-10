<?php
// Solo lectura (10/11/2026): estado de cada conversación de WhatsApp de las últimas N horas.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$horas = (int) ($argv[1] ?? 12);
$fallos = [];
foreach ([date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))] as $d) {
    $f = storage_path("logs/laravel-{$d}.log");
    if (!is_file($f)) { continue; }
    foreach (file($f) as $l) {
        if (strpos($l, 'NO entregado') !== false && preg_match('/^\[([^\]]+)\].*"telefono":"(\d+)"/', $l, $m)) { $fallos[$m[2]] = $m[1]; }
    }
}
$equipo = ['961910398'];
foreach (DB::table('equipo_config')->pluck('telefono') as $t) { $equipo[] = substr(preg_replace('/\D/', '', (string) $t), -9); }
$limpia = function ($t) {
    $t = preg_replace('/\[Sistema[^\]]*\]/u', '', (string) $t);
    $t = str_replace(['[Cliente, mientras lo atendía el equipo]:', '[Imagen recibida]:'], ['»', '[img]'], $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
};
$filas = [];
foreach (DB::table('bot_conversaciones')->where('canal', 'whatsapp')->where('updated_at', '>=', now()->subHours($horas))->orderBy('updated_at', 'desc')->get() as $c) {
    if (in_array(substr($c->usuario_id, -9), $equipo, true)) { continue; }
    $h = json_decode($c->historial_json ?: '[]', true) ?: [];
    if (!$h) { continue; }
    $u = end($h);
    $cli = ''; $bot = '';
    foreach (array_reverse($h) as $t) {
        if (!$cli && $t['role'] === 'user') { $cli = $limpia($t['content']); }
        if (!$bot && $t['role'] === 'assistant') { $bot = $limpia($t['content']); }
        if ($cli && $bot) { break; }
    }
    $todo = mb_strtolower($cli);
    $tags = [];
    if (preg_match('/transf|comprobante|pagu|abon|pago|\[img\]|\[image\]|\[document\]/u', $todo)) { $tags[] = 'PAGO'; }
    if (preg_match('/\b(1\d|[2-9]\d)\s*(personas|pers|pax)\b|empresa|grupo|colegio|iglesia/u', $todo)) { $tags[] = 'GRUPO'; }
    if (preg_match('/persona|ejecutiv|humano|hablar con/u', $todo)) { $tags[] = 'PIDE_PERSONA'; }
    $pausa = $c->humano_hasta && \Carbon\Carbon::parse($c->humano_hasta)->gt(now());
    $fallo = isset($fallos[$c->usuario_id]);
    if ($u['role'] === 'user') { $estado = $pausa ? 'ESPERA-EQUIPO' : 'SIN-RESPUESTA'; }
    elseif ($fallo && strtotime($fallos[$c->usuario_id]) >= strtotime($c->updated_at) - 120) { $estado = 'NO-LLEGO'; }
    else { $estado = strpos((string) $u['content'], '[Equipo Botacura respondió]') !== false ? 'EQUIPO-OK' : 'BOT-OK'; }
    $filas[] = [$estado, $c, $tags, $cli, $bot];
}
$orden = ['SIN-RESPUESTA' => 0, 'NO-LLEGO' => 1, 'ESPERA-EQUIPO' => 2, 'BOT-OK' => 3, 'EQUIPO-OK' => 4];
usort($filas, function ($a, $b) use ($orden) { return $orden[$a[0]] <=> $orden[$b[0]]; });
$cuenta = array_count_values(array_column($filas, 0));
foreach ($filas as $f) {
    list($e, $c, $tags, $cli, $bot) = $f;
    $min = \Carbon\Carbon::parse($c->updated_at)->diffInMinutes(now());
    printf("%-13s #%-5d +%s %-18s hace %4dm %s\n   cliente: %s\n   último bot/equipo: %s\n", $e, $c->id, $c->usuario_id, mb_substr((string) $c->nombre_cliente, 0, 18), $min,
        $tags ? '[' . implode(',', $tags) . ']' : '', mb_substr($cli, -170), mb_substr($bot, 0, 150));
}
echo "\nRESUMEN últimas {$horas} h: ";
foreach ($orden as $k => $x) { echo $k . ' ' . ($cuenta[$k] ?? 0) . ' · '; }
echo "\n";
