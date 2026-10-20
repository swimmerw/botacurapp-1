<?php
// Registra el IVA de julio 2026 (F29) como pagado hoy: queda en Por pagar › Pagadas y en egresos de octubre.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$nombre = 'IVA julio 2026 (F29)';
$d = DB::table('fin_deudas')->where('nombre', $nombre)->first();
if (!$d) {
    $catId = DB::table('categorias_compras')->where('nombre', 'Impuestos y obligaciones')->value('id');
    $subId = $catId ? DB::table('subcategorias_compras')->where('categoria_id', $catId)->where('nombre', 'IVA y F29')->value('id') : null;
    $id = DB::table('fin_deudas')->insertGetId(['tipo' => 'impuesto', 'nombre' => $nombre, 'acreedor' => 'Tesorería · F29', 'patron' => 'f29',
        'ambito' => 'botacura', 'categoria_id' => $catId, 'subcategoria_id' => $subId, 'monto_total' => 755146, 'estado' => 'pendiente',
        'nota' => 'Pagado el 20-10-2026', 'created_at' => now(), 'updated_at' => now()]);
    $d = DB::table('fin_deudas')->find($id);
}
$svc = app(App\Finanzas\Egresos\Deudas::class);
if ($svc->saldo($d) > 0) {
    $svc->registrarPago($d, $svc->saldo($d), '2026-10-20', 'transferencia', null, 'IVA de julio');
}
echo "$nombre: \$755.146 · " . DB::table('fin_deudas')->where('id', $d->id)->value('estado') . "\n";
$r = $svc->resumen(['botacura', 'personal']);
echo 'Deuda pendiente total: $' . number_format($r['total'], 0, ',', '.') . "\n";
foreach ($r['por_tipo'] as $t => $x) echo "  $t: \$" . number_format($x['total'], 0, ',', '.') . " ({$x['n']})\n";
foreach ($r['meses'] as $m) echo "  {$m['mes']}: +" . number_format($m['suma'], 0, ',', '.') . ' −' . number_format($m['pago'], 0, ',', '.') . ' = $' . number_format($m['saldo'], 0, ',', '.') . "\n";
echo 'Se suma cada mes (aprox.): $' . number_format($r['mensual_total'], 0, ',', '.') . "\n";
