<?php
// Piscina en Relax Day y Full Day (como dice el afiche) y re-sincroniza la descripción de los programas en la web.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach ([28, 48] as $id) {
    $p = App\Programa::with('servicios')->find($id);
    if ($p && !$p->servicios->contains('id', 9)) { $p->servicios()->attach(9); echo "  {$p->nombre_programa}: + Piscina\n"; }
}
$wc = app(App\Services\WooCommerceService::class);
foreach ([39, 1, 28, 48, 2] as $id) {
    $p = App\Programa::with('servicios')->find($id);
    try { $wc->updateProduct($p); echo "  web: {$p->nombre_programa} \$".number_format($p->valor_programa,0,',','.')." ✓\n"; }
    catch (\Throwable $e) { echo "  web: {$p->nombre_programa} ERROR ".substr($e->getMessage(),0,150)."\n"; }
}
