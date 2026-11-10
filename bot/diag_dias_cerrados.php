<?php
// Solo lectura: por qué el sistema ve cupo en días que en la práctica están cerrados (10/11/2026).
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

function t($l, $fn) { try { echo $l . ': ' . json_encode($fn(), JSON_UNESCAPED_UNICODE) . "\n"; } catch (\Throwable $e) { echo $l . ': (no se pudo: ' . mb_substr($e->getMessage(), 0, 120) . ")\n"; } }
$fechas = array_slice($argv, 1) ?: ['2026-11-14', '2026-11-21'];
$svc = app(\App\Services\DisponibilidadService::class);
echo "cantidad_slot_spa=" . var_export(config('app.cantidad_slot_spa'), true) . "\n";
foreach ($fechas as $f) {
    echo "\n===== $f =====\n";
    $fd = DB::table('fecha_disponibles')->where('fecha', $f)->get();
    echo "fecha_disponibles: " . json_encode($fd, JSON_UNESCAPED_UNICODE) . "\n";
    $res = DB::table('reservas as r')->leftJoin('programas as p', 'p.id', '=', 'r.id_programa')
        ->whereRaw('DATE(r.fecha_visita) = ?', [$f])
        ->select('r.id', 'r.estado', 'r.cantidad_personas', 'p.nombre_programa', 'p.espacio_tipo')->get();
    $act = $res->filter(function ($r) { return $r->estado !== 'cancelada'; });
    echo "reservas: " . $res->count() . " (activas " . $act->count() . ", personas " . $act->sum('cantidad_personas') . ")\n";
    foreach ($act as $r) { echo "  #{$r->id} {$r->cantidad_personas}p {$r->nombre_programa} [" . ($r->espacio_tipo ?? '-') . "] estado=" . ($r->estado ?? 'null') . "\n"; }
    t('visitas con ubicación', function () use ($f) { return DB::table('visitas as v')->join('reservas as r', 'r.id', '=', 'v.id_reserva')->whereRaw('DATE(r.fecha_visita) = ?', [$f])->whereNotNull('v.id_ubicacion')->count(); });
    t('holds activos', function () use ($f) { return DB::table('reserva_holds')->where('estado', 'activo')->where('fecha', $f)->count(); });
    echo "ordenes web pendientes: " . DB::table('woocommerce_orders')->whereNull('reserva_id')->where('procesado', 'pendiente')->whereRaw('DATE(fecha_visita_wc) = ?', [$f])->count() . "\n";
    echo "slotsTinaja(2): " . json_encode($svc->slotsTinaja($f, 2)) . "\n";
    echo "capacidadLibre: " . json_encode($svc->capacidadLibre($f)) . "\n";
    foreach (['estacion_full', 'estacion_intermedio', 'wellness'] as $t) {
        $d = $svc->disponible($f, $t, 2);
        echo "disponible($t, 2): " . ($d['disponible'] ? 'SÍ' : 'NO') . " · espacio " . json_encode($d['espacio']) . " · " . ($d['razon'] ?? '') . "\n";
    }
    try {
        $r = app(\App\Http\Controllers\Api\FechasDisponiblesController::class)->index(new \Illuminate\Http\Request());
        $lista = array_column(json_decode($r->getContent(), true)['fechas'] ?? [], null);
        echo "en /api/fechas-disponibles: " . (in_array($f, array_map(function ($x) { return is_array($x) ? ($x['fecha'] ?? '') : $x; }, $lista), true) ? 'SÍ' : 'NO') . "\n";
    } catch (\Throwable $e) { echo "fechas-disponibles: " . $e->getMessage() . "\n"; }
}
echo "\nCapacidad instalada (ubicaciones activas por sub_tipo): " . json_encode(DB::table('ubicaciones')->where('activo', true)->select('sub_tipo', DB::raw('COUNT(*) n'), DB::raw('SUM(capacidad_max) cap'))->groupBy('sub_tipo')->get()) . "\n";
