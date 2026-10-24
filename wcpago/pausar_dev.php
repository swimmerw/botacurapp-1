<?php
// Pausa el webhook de desarrollo (ngrok), que ya no existe y genera errores de envío en la web.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = new GuzzleHttp\Client(['base_uri' => rtrim(config('woocommerce.store_url'), '/').'/wp-json/wc/v3/', 'auth' => [config('woocommerce.consumer_key'), config('woocommerce.consumer_secret')], 'timeout' => 20, 'http_errors' => false]);
foreach (json_decode((string) $c->get('webhooks')->getBody(), true) ?: [] as $w) {
    if (strpos($w['delivery_url'], 'ngrok') !== false && $w['status'] === 'active') {
        $r = $c->put('webhooks/'.$w['id'], ['json' => ['status' => 'paused']]);
        echo "  webhook #{$w['id']} {$w['name']}: ".($r->getStatusCode() < 300 ? 'pausado' : 'ERROR '.$r->getStatusCode())."\n";
    }
}
