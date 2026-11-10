<?php
// 28/11: (c) mover #2808 de Estación 1 (sobrevendida) a Estación 4; (d) aviso a Camila (Níspero ocupado -> dos estaciones).
//   sin argumento: mueve #2808 y muestra el mensaje a Camila     enviar: además envía el mensaje
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$modo = $argv[1] ?? 'mostrar';
$fecha = '2026-11-28';
$idsDia = DB::table('reservas')->whereRaw('DATE(fecha_visita) = ?', [$fecha])->where(function ($q) { $q->whereNull('estado')->orWhere('estado', '<>', 'cancelada'); })->pluck('id')->all();
$ocupadas = function () use ($idsDia) {
    return array_map('intval', array_unique(array_merge(
        DB::table('reserva_ubicaciones')->whereIn('id_reserva', $idsDia)->pluck('id_ubicacion')->all(),
        DB::table('visitas')->whereIn('id_reserva', $idsDia)->whereNotNull('id_ubicacion')->pluck('id_ubicacion')->all())));
};

echo "== (c) #2808 Estación 1 -> Estación 4\n";
$ru = DB::table('reserva_ubicaciones')->where('id_reserva', 2808)->pluck('id_ubicacion')->map('intval')->all();
if (in_array(13, $ru, true) && !in_array(10, $ru, true)) {
    echo "Ya estaba en Estación 4.\n";
} elseif (!in_array(10, $ru, true)) {
    echo "#2808 ya no está en Estación 1 (ubicaciones: " . implode(',', $ru) . "). No se toca.\n";
} elseif (in_array(13, $ocupadas(), true)) {
    echo "Estación 4 ya no está libre. No se movió; avísame.\n";
} else {
    DB::transaction(function () {
        DB::table('reserva_ubicaciones')->where('id_reserva', 2808)->where('id_ubicacion', 10)->update(['id_ubicacion' => 13, 'updated_at' => now()]);
        DB::table('visitas')->where('id_reserva', 2808)->where('id_ubicacion', 10)->update(['id_ubicacion' => 13, 'updated_at' => now()]);
    });
    echo "Movida. Estación 1 queda solo con #2701 (2 personas).\n";
}

echo "\n== (d) Aviso a Camila (hold #23)\n";
$tel = '56981741646';
$hold = DB::table('reserva_holds')->where('id', 23)->first();
$libresN = array_diff([8, 9], $ocupadas());
$libresE = array_diff([11, 12, 13, 14, 15, 16], $ocupadas());
echo "Hold #23: estado=" . ($hold->estado ?? '?') . " · Nísperos libres: " . count($libresN) . " · estaciones libres: " . count($libresE) . "\n";
if (!$hold || $hold->estado !== 'activo' || count($libresN) > 0 || count($libresE) < 2) {
    echo "La situación cambió: no envío nada. Avísame.\n"; exit;
}
$txt = 'Hola Camila, te escribimos para corregir un detalle de tu reserva del sábado 28 de noviembre: los Nísperos ya estaban reservados ese día, así que estarán en dos estaciones de descanso (el grupo se reparte entre ellas). Todo lo demás se mantiene igual y tu comprobante está en revisión. Disculpa la confusión 🙏';
echo "Mensaje:\n   $txt\n";
if ($modo !== 'enviar') { echo "Para enviar: ... | bash -s -- enviar\n"; exit; }
$c = DB::table('bot_conversaciones')->where('usuario_id', $tel)->orderBy('id', 'desc')->first();
if ($c && strpos((string) $c->historial_json, 'los Nísperos ya estaban reservados') !== false) { echo "Ya se le había enviado.\n"; exit; }
$r = (new GuzzleHttp\Client(['timeout' => 20, 'http_errors' => false]))->post('https://graph.facebook.com/' . env('META_API_VERSION', 'v26.0') . '/' . env('META_PHONE_NUMBER_ID') . '/messages', [
    'headers' => ['Authorization' => 'Bearer ' . env('META_WHATSAPP_TOKEN'), 'Content-Type' => 'application/json'],
    'json' => ['messaging_product' => 'whatsapp', 'to' => $tel, 'type' => 'text', 'text' => ['body' => $txt]]]);
if ($r->getStatusCode() >= 300) { echo 'ERROR ' . $r->getStatusCode() . ' ' . mb_substr((string) $r->getBody(), 0, 200) . "\n"; exit; }
echo "ENVIADO\n";
if ($c) {
    $h = json_decode($c->historial_json ?: '[]', true) ?: [];
    $nota = '[Equipo Botacura respondió] ' . $txt;
    if ($h && (end($h)['role'] ?? '') === 'assistant') { $h[count($h) - 1]['content'] .= "\n\n" . $nota; } else { $h[] = ['role' => 'assistant', 'content' => $nota]; }
    DB::table('bot_conversaciones')->where('id', $c->id)->update(['historial_json' => json_encode($h, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
}
