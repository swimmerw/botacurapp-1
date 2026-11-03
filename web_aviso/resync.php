<?php
// Vuelve a publicar en WooCommerce solo las fichas que todavía muestran el aviso de noviembre.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$marca = 'Desde noviembre atendemos';
$wc = app(App\Services\WooCommerceService::class);
$client = (function () { return $this->client; })->call($wc);
$vinculados = [];
foreach (App\Programa::whereNotNull('wc_product_id')->get() as $p) {
    $vinculados[(int) $p->wc_product_id] = true;
    try {
        $prod = $wc->getProduct((int) $p->wc_product_id);
        $desc = ($prod['description'] ?? '') . ($prod['short_description'] ?? '');
        if (strpos($desc, $marca) === false) { continue; }
        $wc->updateProduct($p->load('servicios'));
        $prod = $wc->getProduct((int) $p->wc_product_id);
        $ok = strpos(($prod['description'] ?? ''), $marca) === false;
        echo "  {$p->nombre_programa}: " . ($ok ? 'aviso quitado ✓' : 'SIGUE EL AVISO') . "\n";
    } catch (\Throwable $e) {
        echo "  {$p->nombre_programa}: ERROR " . substr($e->getMessage(), 0, 150) . "\n";
    }
}
// Productos de la tienda que no vienen de la app y que también tienen el aviso (se avisan, no se tocan)
try {
    $r = json_decode((string) $client->get('products', ['query' => ['search' => 'noviembre', 'per_page' => 100, 'status' => 'any']])->getBody(), true) ?: [];
    foreach ($r as $prod) {
        if (!isset($vinculados[(int) $prod['id']]) && strpos(($prod['description'] ?? '') . ($prod['short_description'] ?? ''), $marca) !== false) {
            echo "  REVISAR A MANO (no viene de la app): producto {$prod['id']} {$prod['name']}\n";
        }
    }
} catch (\Throwable $e) {
    echo "  (no pude listar productos: " . substr($e->getMessage(), 0, 100) . ")\n";
}
