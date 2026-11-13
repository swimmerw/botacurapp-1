<?php
// Gestión Consumo (13/11/2026): ventas de hoy y futuras que aparecen "cerradas" sin haberse cerrado.
// Una venta cerrada tiene total_pagar = 0; si tiene diferencia y aún total_pagar > 0, no se cerró.
//   sin argumento: diagnóstico     |   arreglar: reabre las de hoy y futuras
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
echo "== Arreglo de ayer instalado:\n";
foreach (['app/Http/Controllers/Api/BotReservaController.php', 'app/Http/Controllers/Api/BotController.php'] as $f) {
    echo '  ' . basename($f) . ': ' . (strpos(file_get_contents(base_path($f)), 'patch_venta_abierta_1211') !== false ? 'SÍ' : 'NO') . "\n";
}
foreach (glob(base_path('app/Http/Controllers/*ReservasDelDia*Controller.php')) as $f) {
    echo '  ' . basename($f) . ': ' . (strpos(file_get_contents($f), 'patch_venta_abierta_1211') !== false ? 'SÍ' : 'NO') . "\n";
}
echo "\n== Código que guarda un valor en diferencia_programa (fuera de respaldos):\n";
echo shell_exec("grep -rn \"diferencia_programa'\\s*=>\" " . base_path('app') . " --include=*.php | grep -v '\\.bak' | grep -v '=> null' | sed 's#" . base_path() . "/##' | cut -c1-170");
$q = DB::table('ventas as v')->join('reservas as r', 'r.id', '=', 'v.id_reserva')->leftJoin('clientes as c', 'c.id', '=', 'r.cliente_id')
    ->whereNotNull('v.diferencia_programa')->where('v.total_pagar', '>', 0)->whereRaw('DATE(r.fecha_visita) >= CURDATE()')
    ->where(function ($w) { $w->whereNull('r.estado')->orWhere('r.estado', '<>', 'cancelada'); });
$filas = (clone $q)->select('v.id', 'r.id as reserva', 'r.fecha_visita', 'r.fuente', 'r.created_at', 'c.nombre_cliente', 'v.abono_programa', 'v.diferencia_programa', 'v.total_pagar', 'v.estado_pago')->orderBy('r.fecha_visita')->get();
echo "\n== Ventas de hoy y futuras que aparecen cerradas sin cerrarse: " . $filas->count() . "\n";
foreach ($filas as $f) {
    echo "  #{$f->reserva} {$f->fecha_visita} " . str_pad($f->fuente ?: '-', 13) . ' creada ' . substr($f->created_at, 0, 16) . ' · ' . mb_substr($f->nombre_cliente, 0, 26)
        . " · abono {$f->abono_programa} dif {$f->diferencia_programa} por cobrar {$f->total_pagar} ({$f->estado_pago})\n";
}
if (($argv[1] ?? '') === 'arreglar' && $filas->count()) {
    file_put_contents(storage_path('app/ventas_reabiertas_' . date('YmdHi') . '.json'), json_encode($filas->map(function ($f) { return ['id' => $f->id, 'dif' => $f->diferencia_programa]; })));
    $n = DB::table('ventas')->whereIn('id', $filas->pluck('id')->all())->update(['diferencia_programa' => null, 'updated_at' => now()]);
    echo "\nREABIERTAS: $n (respaldo en storage/app/ventas_reabiertas_*.json)\n";
}
