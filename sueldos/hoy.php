<?php
// Avisos del banco de hoy y ayer, y cómo quedaron.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (Illuminate\Support\Facades\Schema::hasTable('subcategorias_compras')) {
    $cat = DB::table('categorias_compras')->where('nombre', 'Insumos')->value('id');
    if ($cat && !DB::table('subcategorias_compras')->where('categoria_id', $cat)->where('nombre', 'Café')->exists()) {
        DB::table('subcategorias_compras')->insert(['nombre' => 'Café', 'categoria_id' => $cat, 'created_at' => now(), 'updated_at' => now()]);
        echo "Subcategoría Insumos › Café creada\n";
    }
}
$filas = DB::table('fin_correos')->where(function ($q) { $q->where('fecha', '>=', date('Y-m-d', strtotime('-1 day')))->orWhere('recibido_at', '>=', date('Y-m-d 00:00:00', strtotime('-1 day'))); })
    ->whereNotIn('tipo', ['abono'])->orderBy('recibido_at')->get();
echo 'Salidas avisadas ayer y hoy: ' . count($filas) . "\n";
foreach ($filas as $c) {
    echo "  #{$c->id} " . substr((string) $c->recibido_at, 5, 11) . " {$c->tipo} \$" . number_format((int) $c->monto, 0, ',', '.') . ' ' . mb_substr((string) $c->nombre, 0, 30)
        . " · {$c->estado}/" . ($c->vinculo ?: '-') . ' · ' . mb_substr((string) $c->nota, 0, 90) . "\n";
}
$c = DB::table('fin_correos')->where('cuenta', 'like', '%23674351')->get();
foreach ($c as $x) echo "  Café: #{$x->id} {$x->fecha} \$" . number_format((int) $x->monto, 0, ',', '.') . " · {$x->estado}/" . ($x->vinculo ?: '-') . "\n";
echo "Último aviso guardado por buzón:\n";
foreach (DB::table('fin_correos')->selectRaw('buzon, max(recibido_at) ultimo, sum(recibido_at >= ?) hoy', [date('Y-m-d 00:00:00')])->groupBy('buzon')->get() as $b) {
    echo '  ' . ($b->buzon ?: '(sin buzón)') . ": último {$b->ultimo} · hoy {$b->hoy}\n";
}
