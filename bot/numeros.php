<?php
// Solo lectura: cuentas de WhatsApp y números que ve el token permanente, para saber si el 7448 4112 ya quedó conectado.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$v = env('META_API_VERSION', 'v19.0'); $tok = env('META_WHATSAPP_TOKEN');
$http = new GuzzleHttp\Client(['http_errors' => false, 'timeout' => 20]);
$get = function ($path, $q = []) use ($http, $v, $tok) {
    return json_decode((string) $http->get("https://graph.facebook.com/$v/$path", ['query' => $q + ['access_token' => $tok]])->getBody(), true) ?: [];
};
$dbg = json_decode((string) $http->get("https://graph.facebook.com/$v/debug_token", ['query' => ['input_token' => $tok, 'access_token' => env('META_APP_ID') . '|' . env('META_APP_SECRET')]])->getBody(), true);
$wabas = [];
foreach (($dbg['data']['granular_scopes'] ?? []) as $s) {
    if (in_array($s['scope'], ['whatsapp_business_management', 'whatsapp_business_messaging'], true)) {
        foreach (($s['target_ids'] ?? []) as $id) $wabas[$id] = true;
    }
}
// Además: la cuenta conocida y las de cada negocio al que tiene acceso el token
$wabas[env('HA_WABA_ID', '27846116655070945')] = true;
foreach (($get('me/businesses', ['fields' => 'id,name'])['data'] ?? []) as $b) {
    echo "Negocio {$b['id']} ({$b['name']})\n";
    foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $rel) {
        foreach (($get("{$b['id']}/$rel", ['fields' => 'id,name'])['data'] ?? []) as $w) $wabas[$w['id']] = true;
    }
}
foreach (array_keys($wabas) as $w) {
    $info = $get($w, ['fields' => 'name']);
    echo "Cuenta $w (" . ($info['name'] ?? '?') . ")\n";
    $nums = $get("$w/phone_numbers", ['fields' => 'display_phone_number,verified_name,is_on_biz_app,platform_type,code_verification_status,status']);
    foreach (($nums['data'] ?? []) as $n) {
        echo "  " . $n['display_phone_number'] . " · id " . $n['id'] . " · " . ($n['verified_name'] ?? '') . " · app WhatsApp Business: " . (!empty($n['is_on_biz_app']) ? 'sí (coexistencia)' : 'no')
            . " · " . ($n['platform_type'] ?? '') . " · " . ($n['status'] ?? '') . "\n";
        if (strpos(preg_replace('/\D/', '', $n['display_phone_number']), '74484112') !== false) echo "  → ESTE es el 7448 4112: id {$n['id']}\n";
    }
    if (isset($nums['error'])) echo "  error: " . $nums['error']['message'] . "\n";
    $subs = $get("$w/subscribed_apps");
    echo "  App suscrita: " . (!empty($subs['data']) ? 'sí' : 'no') . "\n";
}
