<?php
// Reabre las ventas de reservas del bot que quedaron "cerradas" sin haberse cerrado (12/11/2026).
//   sin argumento: hoy y fechas futuras    |   pasadas: también las anteriores a hoy (muestra primero)
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$base = DB::table('ventas as v')->join('reservas as r', 'r.id', '=', 'v.id_reserva')
    ->where('r.fuente', 'like', 'bot%')->whereNotNull('v.diferencia_programa')
    ->whereColumn('v.diferencia_programa', 'v.total_pagar')->where('v.total_pagar', '>', 0);
$futuras = (clone $base)->whereRaw('DATE(r.fecha_visita) >= CURDATE()');
$ids = $futuras->pluck('v.id')->all();
file_put_contents(storage_path('app/ventas_reabiertas_' . date('YmdHi') . '.json'), json_encode($ids));
$n = $ids ? DB::table('ventas')->whereIn('id', $ids)->update(['diferencia_programa' => null, 'updated_at' => now()]) : 0;
echo "Ventas del bot reabiertas (hoy y futuras): $n\n";
$pasadas = (clone $base)->whereRaw('DATE(r.fecha_visita) < CURDATE()')->select('v.id', 'r.id as reserva', 'r.fecha_visita', 'v.total_pagar')->orderBy('r.fecha_visita')->get();
echo "Ventas del bot de días ANTERIORES que quedaron como cerradas sin cerrarse: " . $pasadas->count() . "\n";
foreach ($pasadas as $p) echo "  reserva #{$p->reserva} {$p->fecha_visita} saldo \${$p->total_pagar}\n";
if (($argv[1] ?? '') === 'pasadas' && $pasadas->count()) {
    DB::table('ventas')->whereIn('id', $pasadas->pluck('id')->all())->update(['diferencia_programa' => null, 'updated_at' => now()]);
    echo "Reabiertas también las anteriores: aparecerán en Gestión Consumo de su día para cerrarlas.\n";
}
