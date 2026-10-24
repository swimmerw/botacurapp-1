<?php
// Cambia la imagen principal de cada programa en botacura.cl por el afiche nuevo (valor por persona, lunes a domingo),
// deja las demás fotos de la galería, saca Desayuno/Once de la tienda y pone "Mínimo 2 personas" en las fichas.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = new GuzzleHttp\Client(['base_uri' => rtrim(config('woocommerce.store_url'), '/').'/wp-json/wc/v3/',
    'auth' => [config('woocommerce.consumer_key'), config('woocommerce.consumer_secret')], 'timeout' => 90, 'http_errors' => false]);
$base = rtrim(config('app.url'), '/').'/infografias/';
$mapa = [39 => 'wellness-day', 1 => 'wellness-plus', 28 => 'relax-day', 48 => 'full-day', 2 => 'botacura-full'];
foreach ($mapa as $id => $slug) {
    $p = App\Programa::find($id);
    if (!$p || !$p->wc_product_id) { echo "  #$id sin producto en la web\n"; continue; }
    $r = $c->get("products/{$p->wc_product_id}"); $prod = json_decode((string) $r->getBody(), true);
    $galeria = array_map(function ($i) { return ['id' => $i['id']]; }, array_slice($prod['images'] ?? [], $slug === 'botacura-full' ? 0 : 1)); // Botacura Full no tenía afiche: se conservan todas sus fotos
    $src = $base."afiche-$slug-noviembre.jpg?v=".date('YmdHis');
    $r = $c->put("products/{$p->wc_product_id}", ['json' => ['images' => array_merge([['src' => $src, 'name' => "Afiche {$p->nombre_programa} noviembre", 'alt' => "{$p->nombre_programa}: valor por persona, Botacura"]], $galeria)]]);
    $b = json_decode((string) $r->getBody(), true);
    echo "  {$p->nombre_programa}: ".($r->getStatusCode() < 300 ? 'afiche nuevo ✓ ('.count($galeria).' fotos más en la galería)' : 'ERROR '.$r->getStatusCode().' '.substr($b['message'] ?? '', 0, 150))."\n";
    if ($r->getStatusCode() < 300) { $p->imagen_url = $base."afiche-$slug-noviembre.jpg"; $p->save(); }
}
// Desayuno/Once fuera de la tienda (queda en la app)
$d = App\Programa::find(54);
if ($d && !$d->solo_plataforma) {
    $d->solo_plataforma = true; $d->save();
    try { app(App\Services\WooCommerceService::class)->draftProduct($d); echo "  Desayuno/Once: fuera de la tienda ✓\n"; } catch (\Throwable $e) { echo "  Desayuno/Once ERROR ".substr($e->getMessage(), 0, 150)."\n"; }
}
// Fichas con "Mínimo 2 personas"
$wc = app(App\Services\WooCommerceService::class);
foreach ([39, 1, 28, 48, 2] as $id) {
    $p = App\Programa::with('servicios')->find($id);
    try { $wc->updateProduct($p); echo "  ficha {$p->nombre_programa} ✓\n"; } catch (\Throwable $e) { echo "  ficha {$p->nombre_programa} ERROR ".substr($e->getMessage(), 0, 120)."\n"; }
}
