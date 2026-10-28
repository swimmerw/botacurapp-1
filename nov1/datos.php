<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$def = App\Services\BotTarifasService::parametrosDefault();
$k = 'param_fechas_temporada_baja';
if (!DB::table('bot_secciones_texto')->where('clave', $k)->exists())
    DB::table('bot_secciones_texto')->insert(['clave' => $k, 'categoria' => 'parametros', 'etiqueta' => $def[$k][0], 'contenido' => $def[$k][1],
        'orden' => (int) DB::table('bot_secciones_texto')->where('categoria', 'parametros')->max('orden') + 1, 'created_at' => now(), 'updated_at' => now()]);
App\Services\BotTarifasService::limpiarCache();
foreach (['2026-10-31', '2026-11-01', '2026-11-02'] as $f) {
    $c = Carbon\Carbon::parse($f);
    $p = App\Services\BotTarifasService::esTemporadaBaja($c) ? 'octubre' : 'noviembre';
    $v = App\Services\BotTarifasService::precioTemporadaBaja(2);
    echo "  $f: precios de $p".($p === 'octubre' ? " (Botacura Full \$".number_format($v, 0, ',', '.').")" : '')."\n";
}
echo "  Reservas del 1 de noviembre (revisar el valor cobrado):\n";
$rs = DB::table('reservas')->leftJoin('programas', 'programas.id', '=', 'reservas.id_programa')
    ->leftJoin('ventas', 'ventas.id_reserva', '=', 'reservas.id')
    ->whereDate('reservas.fecha_visita', '2026-11-01')
    ->select('reservas.id', 'reservas.cantidad_personas', 'programas.nombre_programa', 'ventas.total_pagar', 'ventas.abono_programa')->get();
foreach ($rs as $r) echo "   #{$r->id} {$r->nombre_programa} · {$r->cantidad_personas} pers · total \$".number_format((int) $r->total_pagar, 0, ',', '.')."\n";
if ($rs->isEmpty()) echo "   (ninguna)\n";
