<?php
// Descripción corta de las fichas de botacura.cl según el afiche de noviembre (Gift Card $110.000) y re-sincroniza la descripción larga.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = new GuzzleHttp\Client(['base_uri' => rtrim(config('woocommerce.store_url'), '/').'/wp-json/wc/v3/',
    'auth' => [config('woocommerce.consumer_key'), config('woocommerce.consumer_secret')], 'timeout' => 60, 'http_errors' => false]);
$dias = ' Atendemos de lunes a domingo, de 10:00 a 19:00 hrs (de lunes a miércoles, con grupos desde 10 personas). Mínimo 2 personas.';
$textos = [
  39 => 'Tinaja (45 min), sauna (15 min), almuerzo a la carta (fondo, ensalada y postre) y piscina. $50.000 por persona.',
  1  => 'Tinaja (45 min), sauna (15 min), desayuno buffet u once buffet, almuerzo a la carta, piscina y reposera. $55.000 por persona.',
  28 => 'Tinaja (45 min), sauna (15 min), masaje de relajación de 30 min, almuerzo a la carta, piscina y estación de descanso. $75.000 por persona.',
  48 => 'Tinaja (45 min), sauna (15 min), masaje de relajación de 30 min, desayuno buffet u once buffet, almuerzo a la carta, piscina y estación de descanso. $85.000 por persona.',
  2  => 'Nuestro programa más completo: tinaja (45 min), sauna (15 min), masaje de relajación de 60 min, desayuno buffet u once buffet, almuerzo a la carta, piscina, espumante de 375 ml por pareja y estación de descanso. $110.000 por persona. También como Gift Card ($110.000 por persona).',
];
$respaldo = [];
$wc = app(App\Services\WooCommerceService::class);
foreach ($textos as $id => $t) {
    $p = App\Programa::with('servicios')->find($id);
    if (!$p || !$p->wc_product_id) { echo "  #$id sin producto en la web\n"; continue; }
    $prod = json_decode((string) $c->get("products/{$p->wc_product_id}")->getBody(), true);
    $respaldo[$id] = $prod['short_description'] ?? '';
    echo "  {$p->nombre_programa} antes: ".trim(strip_tags($respaldo[$id]))."\n";
    $r = $c->put("products/{$p->wc_product_id}", ['json' => ['short_description' => '<p>'.$t.$dias.'</p>']]);
    echo "  {$p->nombre_programa}: ".($r->getStatusCode() < 300 ? 'descripción corta ✓' : 'ERROR '.$r->getStatusCode())."\n";
    try { $wc->updateProduct($p); echo "  ficha {$p->nombre_programa} ✓\n"; } catch (\Throwable $e) { echo "  ficha ERROR ".substr($e->getMessage(), 0, 120)."\n"; }
}
file_put_contents(storage_path('app/respaldo_short_description_'.date('YmdHis').'.json'), json_encode($respaldo, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
// Otros productos con "gift" en el nombre (solo para revisar)
$g = json_decode((string) $c->get('products', ['query' => ['search' => 'gift', 'per_page' => 20]])->getBody(), true);
foreach ((array) $g as $x) echo "  REVISAR producto #{$x['id']} {$x['name']} \${$x['price']} ({$x['status']}): ".substr(trim(strip_tags($x['short_description'] ?? '')), 0, 160)."\n";
