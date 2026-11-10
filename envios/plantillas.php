<?php
// Plantillas de WhatsApp para confirmar o rechazar el comprobante fuera de la ventana de 24 h (10/11/2026).
// Busca la cuenta de WhatsApp del número del bot, crea las plantillas si no existen y muestra su estado.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$v = env('META_API_VERSION', 'v21.0'); $tok = env('META_WHATSAPP_TOKEN'); $phoneId = env('META_PHONE_NUMBER_ID');
$http = new GuzzleHttp\Client(['http_errors' => false, 'timeout' => 20]);
$get = function ($path, $q = []) use ($http, $v, $tok) {
    return json_decode((string) $http->get("https://graph.facebook.com/$v/$path", ['query' => $q + ['access_token' => $tok]])->getBody(), true) ?: [];
};

// 1) Cuenta de WhatsApp (WABA) del número del bot
$waba = env('WA_WABA_ID');
if (!$waba) {
    $cands = [env('HA_WABA_ID', '27846116655070945') => true];
    foreach (($get('me/businesses', ['fields' => 'id'])['data'] ?? []) as $b) {
        foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $rel) {
            foreach (($get("{$b['id']}/$rel", ['fields' => 'id'])['data'] ?? []) as $w) { $cands[$w['id']] = true; }
        }
    }
    foreach (array_keys($cands) as $w) {
        foreach (($get("$w/phone_numbers", ['fields' => 'id,display_phone_number'])['data'] ?? []) as $n) {
            if ((string) $n['id'] === (string) $phoneId) { $waba = $w; echo "Número del bot: {$n['display_phone_number']} · cuenta $w\n"; break 2; }
        }
    }
}
if (!$waba) { echo "ERROR: no encontré la cuenta de WhatsApp del número del bot ($phoneId). Agrega WA_WABA_ID al .env.\n"; exit(1); }
echo "WABA_ID $waba\n";

$menu = preg_replace('/\?.*$/', '', (string) (\App\Services\BotMenuService::url() ?: url('/docs/menu-otono-2026.pdf')));
$plantillas = [
    [
        'name' => \App\Services\WaEnviosService::PLANTILLA_CONFIRMACION, 'language' => 'es', 'category' => 'UTILITY',
        'components' => [
            ['type' => 'BODY',
             'text' => "¡Hola {{1}}! 🎉 Verificamos tu pago y tu reserva N°{{2}} para el {{3}} quedó confirmada.\n\nEn el botón está el menú para que elijan sus opciones. Respóndenos por este chat y te enviamos la información para tu visita. ¡Te esperamos en Botacura! 🌿",
             'example' => ['body_text' => [['Camila', '2801', 'sábado 14 de noviembre']]]],
            ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'Ver menú', 'url' => $menu]]],
        ],
    ],
    [
        'name' => \App\Services\WaEnviosService::PLANTILLA_RECHAZO, 'language' => 'es', 'category' => 'UTILITY',
        'components' => [
            ['type' => 'BODY',
             'text' => "Hola {{1}}, no pudimos verificar el comprobante de tu reserva N°{{2}}. Por favor envíanos por este chat una foto clara del comprobante de transferencia para confirmarla. 🙏",
             'example' => ['body_text' => [['Camila', '2801']]]],
        ],
    ],
];

foreach ($plantillas as $p) {
    $ex = $get("$waba/message_templates", ['name' => $p['name'], 'fields' => 'name,status,language,rejected_reason']);
    $actual = collect($ex['data'] ?? [])->firstWhere('language', $p['language']);
    if ($actual) {
        echo "Plantilla {$p['name']}: ya existe · estado {$actual['status']}" . (!empty($actual['rejected_reason']) && $actual['rejected_reason'] !== 'NONE' ? " · motivo {$actual['rejected_reason']}" : '') . "\n";
        continue;
    }
    $r = $http->post("https://graph.facebook.com/$v/$waba/message_templates", ['query' => ['access_token' => $tok], 'json' => $p]);
    $d = json_decode((string) $r->getBody(), true) ?: [];
    if (!empty($d['id'])) {
        echo "Plantilla {$p['name']}: creada · estado " . ($d['status'] ?? 'PENDING') . " (Meta la revisa; suele tardar de minutos a un día)\n";
    } else {
        echo "Plantilla {$p['name']}: ERROR al crear · " . ($d['error']['error_user_msg'] ?? $d['error']['message'] ?? (string) $r->getBody()) . "\n";
    }
}
echo "Botón del menú: $menu\n";
