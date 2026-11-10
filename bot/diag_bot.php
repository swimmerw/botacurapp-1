<?php
// Solo lectura (10/11/2026): por que el bot no responde.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$http = new GuzzleHttp\Client(['timeout' => 40, 'http_errors' => false]);
$ok = function ($b) { return $b ? 'OK ' : 'FALLA '; };

echo "== 1. Bot encendido: " . (\App\BotSeccionTexto::where('clave', 'bot_activo')->value('contenido') === '0' ? 'APAGADO en Configuración del bot' : 'sí') . "\n";

$m = config('services.anthropic.model'); $t0 = microtime(true);
$r = $http->post('https://api.anthropic.com/v1/messages', ['headers' => ['x-api-key' => config('services.anthropic.key'), 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json'],
    'json' => ['model' => $m, 'max_tokens' => 300, 'system' => 'Responde SOLO con JSON {"accion":"responder","mensaje":"...","datos":{}}', 'messages' => [['role' => 'user', 'content' => 'Hola, ¿tienen cupo el domingo 22 para 2?']]]]);
$b = json_decode((string) $r->getBody(), true);
echo "== 2. Modelo {$m}: " . $ok($r->getStatusCode() === 200) . "HTTP " . $r->getStatusCode() . ' en ' . round(microtime(true) - $t0, 1) . ' s'
    . ($r->getStatusCode() !== 200 ? ' · ' . mb_substr((string) $r->getBody(), 0, 300) : '') . "\n";

$v = env('META_API_VERSION', 'v26.0'); $tok = env('META_WHATSAPP_TOKEN'); $pid = env('META_PHONE_NUMBER_ID');
$p = json_decode((string) $http->get("https://graph.facebook.com/{$v}/{$pid}", ['query' => ['fields' => 'display_phone_number,status,quality_rating,messaging_limit_tier,platform_type,is_on_biz_app,account_mode,health_status', 'access_token' => $tok]])->getBody(), true);
echo "== 3. Número de Meta {$pid}: " . mb_substr(json_encode($p, JSON_UNESCAPED_UNICODE), 0, 900) . "\n";
$w = DB::table('bot_conversaciones')->value('id') ? null : null;

$L = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
$lin = array_slice(array_values(array_filter(file($L), function ($l) { return strpos($l, 'WA sync') === false && strpos($l, 'Verificación fallida') === false; })), -4000);
$ult = function ($pat) use ($lin) { $x = array_values(array_filter($lin, function ($l) use ($pat) { return preg_match($pat, $l); })); return $x ? substr(end($x), 1, 19) : 'nunca hoy'; };
echo "== 4. Último mensaje recibido de un cliente: " . $ult('/\[WhatsApp\] Mensaje tipo=/') . "\n";
echo "   Última respuesta del bot: " . $ult('/\[WhatsApp\] Bot response/') . "\n";
echo "   Último mensaje que el bot dejó en espera (chat pausado o bot apagado): " . $ult('/mensaje_en_espera|Mensaje atrasado|Tipo ignorado/') . "\n";
echo "== 5. Errores de hoy (últimos):\n";
foreach (array_slice(array_values(array_filter($lin, function ($l) { return preg_match('/production\.ERROR|Error enviando mensaje|Claude error|Excepción|timed out|131042|131047|131026/', $l); })), -8) as $l) { echo '   ' . mb_substr(trim($l), 0, 330) . "\n"; }
echo "== 6. Últimas 10 líneas del bot:\n";
foreach (array_slice(array_values(array_filter($lin, function ($l) { return preg_match('/\[WhatsApp\]|\[Bot\]/', $l); })), -10) as $l) { echo '   ' . mb_substr(trim($l), 0, 220) . "\n"; }
echo "== 7. Mensajes en espera de las últimas 2 h: " . \App\BotMensajePendiente::where('created_at', '>=', now()->subHours(2))->count() . " · chats en pausa ahora: " . DB::table('bot_conversaciones')->where('humano_hasta', '>', now())->count() . "\n";
