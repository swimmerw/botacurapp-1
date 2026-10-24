<?php
// Solo lectura: estado de la conexión web (WooCommerce) → app.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
echo "Config: store=".config('woocommerce.store_url')." · key ".(config('woocommerce.consumer_key') ? 'sí' : 'NO')." · secret webhook ".(config('woocommerce.webhook_secret') ? 'sí' : 'NO')." · user_id ".config('woocommerce.system_user_id', 1)."\n";
echo "Columnas woocommerce_orders: ".implode(',', Schema::getColumnListing('woocommerce_orders'))."\n";
echo "Por estado: ".DB::table('woocommerce_orders')->select('procesado', DB::raw('count(*) n'))->groupBy('procesado')->get()->map(function($r){return "$r->procesado=$r->n";})->implode(' · ')."\n\n";
foreach (DB::table('woocommerce_orders')->orderByDesc('id')->limit(15)->get() as $o) {
    $p = json_decode($o->payload_raw, true) ?: [];
    $items = implode(' + ', array_map(function($i){ return ($i['quantity'] ?? '?').'× '.($i['name'] ?? '?').' $'.($i['total'] ?? '?'); }, $p['line_items'] ?? []));
    echo "#{$o->wc_order_id} ".substr($o->created_at,0,16)." visita ".($o->fecha_visita_wc ?: '¿?')." · {$items} · total \${$o->total} · {$o->payment_method} · {$o->procesado}".($o->reserva_id ? " → reserva #{$o->reserva_id}" : '').(!empty($o->error_detalle) ? " · ERROR: ".mb_substr($o->error_detalle,0,140) : '')."\n";
}
echo "\nTrabajos fallidos de WooCommerce: ".DB::table('failed_jobs')->where('payload', 'like', '%ProcesarOrdenWoocommerce%')->count()."\n";
try {
    $c = new GuzzleHttp\Client(['base_uri' => rtrim(config('woocommerce.store_url'),'/').'/wp-json/wc/v3/', 'auth' => [config('woocommerce.consumer_key'), config('woocommerce.consumer_secret')], 'timeout' => 20, 'http_errors' => false]);
    $r = $c->get('webhooks', ['query' => ['per_page' => 50]]);
    echo "\nWebhooks en la web (HTTP {$r->getStatusCode()}):\n";
    foreach (json_decode((string) $r->getBody(), true) ?: [] as $w) echo "  #{$w['id']} {$w['name']} · {$w['topic']} · {$w['status']} → {$w['delivery_url']}\n";
    $r = $c->get('orders', ['query' => ['per_page' => 15]]);
    echo "\nÚltimos pedidos en la web:\n";
    foreach (json_decode((string) $r->getBody(), true) ?: [] as $o) {
        $meta = []; foreach ($o['meta_data'] ?? [] as $m) if (is_scalar($m['value'] ?? null)) $meta[$m['key']] = $m['value'];
        echo "  #{$o['id']} ".substr($o['date_created'],0,16)." {$o['status']} \${$o['total']} {$o['payment_method_title']} · ".implode(' + ', array_map(function($i){return $i['quantity'].'× '.$i['name'];}, $o['line_items']))." · fecha_visita=".($meta['billing_fecha_visita'] ?? '—')." · en app: ".(DB::table('woocommerce_orders')->where('wc_order_id', $o['id'])->exists() ? 'sí' : 'NO')."\n";
    }
    if (!empty($o)) echo "  Claves meta del último pedido: ".implode(', ', array_keys($meta))."\n";
} catch (\Throwable $e) { echo "Error consultando la web: ".$e->getMessage()."\n"; }
