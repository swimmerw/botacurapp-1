<?php
// Simula una conversación (sin enviar nada) para probar el orden: fecha/personas/programa -> tinajas -> ubicación -> confirmar -> datos.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$tel = '56900000011';
$pasos = array_slice($argv, 1) ?: ['Hola, quiero reservar para el sábado 21 de noviembre', 'Somos 2', 'Wellness Plus', 'Prefiero el jueves 26 de noviembre', 'Ana Pérez Soto', 'Sí, ese número', 'ana.prueba@ejemplo.cl'];
DB::table('bot_conversaciones')->where('usuario_id', $tel)->delete();
foreach ($pasos as $msg) {
    $req = Illuminate\Http\Request::create('/api/bot-ai/message', 'POST', ['telefono' => $tel, 'mensaje' => $msg, 'nombre' => 'Prueba', 'simulacion' => true]);
    $req->headers->set('X-Bot-Secret', (string) config('services.bot.secret'));
    $ref = new ReflectionClass(App\Http\Controllers\Api\BotController::class);
    $h = $ref->getConstant('BOT_SECRET_HEADER'); if ($h) { $req->headers->set($h, (string) config('services.bot.secret')); }
    app()->instance('request', $req);
    $r = json_decode((new App\Http\Controllers\Api\BotController())->message($req)->getContent(), true) ?: [];
    echo "\nCLIENTE: $msg\nBOT [" . implode(',', $r['acciones_ejecutadas'] ?? []) . "]: " . ($r['mensaje'] ?? json_encode($r, JSON_UNESCAPED_UNICODE)) . "\n";
}
$ev = DB::table('bot_eventos')->where('telefono', $tel)->where('created_at', '>=', now()->subMinutes(10))->pluck('tipo')->all();
echo "\nEventos de control: " . ($ev ? implode(', ', $ev) : 'ninguno') . "\n";
DB::table('bot_conversaciones')->where('usuario_id', $tel)->delete();
