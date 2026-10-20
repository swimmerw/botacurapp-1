<?php
// Corrige los "Código de Autorización" guardados como abono y marca la semana 12-18/10 de quienes se pagaron el 20/10 sin aviso del banco.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$aplicar = in_array('--aplicar', $argv, true);

$cods = DB::table('fin_correos')->where('asunto', 'like', '%digo de autorizaci%')->where('tipo', '<>', 'otro')->get();
foreach ($cods as $c) {
    echo "Código de autorización #{$c->id} {$c->fecha} \$" . number_format((int) $c->monto, 0, ',', '.') . " guardado como {$c->tipo}/{$c->estado}"
        . ($c->venta_id ? " · OJO: quedó asociado a la venta #{$c->venta_id} (revísalo a mano)" : '') . "\n";
    if (!$c->venta_id) {
        DB::table('fin_correos')->where('id', $c->id)->update(['tipo' => 'otro', 'estado' => 'ignorado', 'monto' => null, 'nota' => 'Código de autorización (no es movimiento)', 'updated_at' => now()]);
    }
}
echo count($cods) ? "Corregidos los que no tenían venta asociada.\n" : "Sin códigos de autorización mal leídos.\n";

echo "\nSemana 12-10 al 18-10 · pagados el 20-10 en la mañana (sin aviso del banco)" . ($aplicar ? '' : ' · SIMULACIÓN') . "\n";
$ss = app(App\Finanzas\SueldoSemanas::class);
foreach (['Alejandro', 'Octavio', 'Natalia', 'Paula', 'Fernando Castro', 'Catalina', 'Catherine'] as $n) {
    $us = DB::table('users')->where('name', 'like', $n . '%')->get(['id', 'name']);
    if ($us->count() !== 1) {
        echo "  $n: " . ($us->count() ? 'varios (' . $us->pluck('name')->implode(', ') . ')' : 'no encontrado') . " · no lo toco\n";
        continue;
    }
    $u = $us->first();
    $sem = collect($ss->pendientes((int) $u->id, '2026-10-20'))->first(function ($w) { return $w['inicio'] === '2026-10-12'; });
    if (!$sem) { echo "  {$u->name}: no tiene días trabajados esa semana\n"; continue; }
    if ($sem['pagado']) { echo "  {$u->name}: ya estaba pagada (\$" . number_format($sem['total'], 0, ',', '.') . ")\n"; continue; }
    if ($aplicar) { $ss->marcarPagada((int) $u->id, $sem, '2026-10-20'); }
    echo "  {$u->name}: \$" . number_format($sem['total'], 0, ',', '.') . ($aplicar ? ' · marcada pagada' : ' · se marcaría pagada') . "\n";
}
