<?php
// Datos para la proyección nov–feb (solo lectura). Escribe storage/app/proyeccion_datos.json y lo imprime.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
$out = ['generado' => now()->toDateTimeString()];
$err = function ($k, $e) use (&$out) { $out['errores'][$k] = mb_substr($e->getMessage(), 0, 200); };

// 1. Mes a mes: misma fórmula que Finanzas › Utilidad
try {
    $rf = app(App\Http\Controllers\ReporteFinancieroController::class);
    $m = new ReflectionMethod($rf, 'calcularUtilidadMes'); $m->setAccessible(true);
    $c = Carbon::create(2024, 11, 1);
    while ($c->lte(now()->startOfMonth())) {
        $u = $m->invoke($rf, $c->year, $c->month);
        $k = $c->format('Y-m');
        $r = DB::table('reservas')->whereYear('fecha_visita', $c->year)->whereMonth('fecha_visita', $c->month)
            ->selectRaw('COUNT(*) n, COALESCE(SUM(cantidad_personas),0) p, COUNT(DISTINCT fecha_visita) d')->first();
        $out['meses'][$k] = ['ing' => $u['totalIngresos'], 'prog' => $u['abonos'], 'gc' => $u['giftCardsVendidasMes'], 'cons' => $u['consumos'],
            'serv' => $u['servicios'], 'dir' => $u['directas'], 'fact' => $u['facturasSii'], 'otros' => $u['otrosEgresos'], 'inv' => $u['inversiones'],
            'ivaD' => $u['ivaDebito'], 'ivaC' => $u['ivaCompras'], 'ret' => $u['honorariosRetencion'], 'hon' => $u['honorariosNeto'], 'sue' => $u['sueldosPagados'],
            'prop' => $u['propinas'], 'ppm' => $u['ppm'], 'util' => $u['utilidad'], 'res' => (int) $r->n, 'pers' => (int) $r->p, 'dias' => (int) $r->d];
        $c->addMonth();
    }
} catch (Throwable $e) { $err('meses', $e); }

$desde = now()->subMonths(12)->startOfMonth()->toDateString();
// 2. Programas (12 meses) y precio actual
try {
    $out['programas'] = DB::table('reservas as r')->join('programas as p', 'p.id', '=', 'r.id_programa')->leftJoin('ventas as v', 'v.id_reserva', '=', 'r.id')
        ->where('r.fecha_visita', '>=', $desde)->groupBy('p.id', 'p.nombre_programa', 'p.valor_programa')
        ->selectRaw('p.id, p.nombre_programa n, p.valor_programa precio, COUNT(r.id) res, SUM(r.cantidad_personas) pers, SUM(COALESCE(v.abono_programa,0)+COALESCE(v.diferencia_programa,0)) ing')
        ->orderByDesc('pers')->get();
} catch (Throwable $e) { $err('programas', $e); }

// 3. Carta: lo vendido en 12 meses, precio y costo de receta
try {
    $costos = Schema::hasTable('insumo_producto') ? DB::table('insumo_producto')->groupBy('id_producto')->selectRaw('id_producto, SUM(total_costo_producto) c')->pluck('c', 'id_producto') : collect();
    $out['carta'] = DB::table('detalles_consumos as d')->join('consumos as c', 'c.id', '=', 'd.id_consumo')->join('productos as p', 'p.id', '=', 'd.id_producto')
        ->leftJoin('tipos_productos as t', 't.id', '=', 'p.id_tipo_producto')->where('c.created_at', '>=', $desde)
        ->groupBy('p.id', 'p.nombre', 'p.valor', 't.nombre')->selectRaw('p.id, p.nombre n, t.nombre tipo, p.valor precio, SUM(d.cantidad_producto) cant, SUM(d.subtotal) ing')
        ->orderByDesc('ing')->limit(60)->get()->map(function ($x) use ($costos) { $x->costo = isset($costos[$x->id]) ? (int) $costos[$x->id] : null; return $x; });
    $out['carta_total_productos'] = DB::table('productos')->count();
} catch (Throwable $e) { $err('carta', $e); }

// 4. Servicios extra (12 meses) y lista de servicios
try {
    $out['servicios_extra'] = DB::table('detalle_servicios_extra as d')->join('consumos as c', 'c.id', '=', 'd.id_consumo')
        ->leftJoin('servicios as s', 's.id', '=', 'd.id_servicio_extra')->where('c.created_at', '>=', $desde)
        ->groupBy('s.nombre_servicio')->selectRaw('s.nombre_servicio n, SUM(d.cantidad_servicio) cant, SUM(d.subtotal) ing')->orderByDesc('ing')->get();
    $out['servicios'] = DB::table('servicios')->select('nombre_servicio', 'valor_servicio', 'costo_servicio')->get();
} catch (Throwable $e) { $err('servicios', $e); }

// 5. Equipo: por mes (personas, días-persona, total) y por persona (últimos 3 meses)
try {
    $sv = new App\Services\SueldoDevengadoService();
    $c = Carbon::create(2024, 11, 1);
    while ($c->lte(now()->startOfMonth())) {
        $det = $sv->detalleMes($c->year, $c->month);
        $dp = DB::table('sueldos')->whereYear('dia_trabajado', $c->year)->whereMonth('dia_trabajado', $c->month)->count();
        $out['equipo_mes'][$c->format('Y-m')] = ['n' => count($det), 'dias_persona' => $dp,
            'base' => array_sum(array_column($det, 'base')), 'bono' => array_sum(array_column($det, 'bono')), 'prop' => array_sum(array_column($det, 'propinas'))];
        $c->addMonth();
    }
    $tres = [];
    for ($i = 1; $i <= 3; $i++) {
        $c = now()->subMonths($i);
        foreach ($sv->detalleMes($c->year, $c->month) as $uid => $d) {
            $dias = DB::table('sueldos')->where('user_id', $uid)->whereYear('dia_trabajado', $c->year)->whereMonth('dia_trabajado', $c->month)->count();
            $t = &$tres[$uid];
            $t['n'] = $d['nombre'] ?? $uid; $t['maso'] = !empty($d['masoterapeuta']);
            $t['base'] = ($t['base'] ?? 0) + (int) $d['base'] + (int) $d['bono']; $t['dias'] = ($t['dias'] ?? 0) + $dias; $t['prop'] = ($t['prop'] ?? 0) + (int) $d['propinas'];
            unset($t);
        }
    }
    foreach ($tres as &$t) { $t['por_dia'] = $t['dias'] ? (int) round($t['base'] / $t['dias']) : null; } unset($t);
    $out['equipo_3m'] = array_values($tres);
} catch (Throwable $e) { $err('equipo', $e); }

// 6. Egresos por categoría (12 meses, panel nuevo)
try {
    $ep = app(App\Http\Controllers\EgresosPanelController::class);
    $m = new ReflectionMethod($ep, 'datos'); $m->setAccessible(true);
    for ($i = 12; $i >= 1; $i--) {
        $c = now()->subMonths($i)->startOfMonth();
        $d = $m->invoke($ep, $c->copy(), $c->copy()->endOfMonth(), true);
        foreach ($d['arbol'] as $cat => $x) {
            foreach ($x['subs'] as $sub => $s) { $out['egresos_cat']["$cat › $sub"][$c->format('Y-m')] = (int) $s['total']; }
        }
    }
} catch (Throwable $e) { $err('egresos', $e); }

// 7. Días habilitados y reservas ya tomadas nov–feb
try {
    for ($c = now()->startOfMonth(); $c->lte(Carbon::create(2027, 2, 1)); $c->addMonth()) {
        $k = $c->format('Y-m');
        $out['agenda'][$k] = [
            'habilitados' => Schema::hasTable('fecha_disponibles') ? DB::table('fecha_disponibles')->whereYear('fecha', $c->year)->whereMonth('fecha', $c->month)->where('habilitada', true)->count() : null,
            'reservas' => DB::table('reservas')->whereYear('fecha_visita', $c->year)->whereMonth('fecha_visita', $c->month)->count(),
            'personas' => (int) DB::table('reservas')->whereYear('fecha_visita', $c->year)->whereMonth('fecha_visita', $c->month)->sum('cantidad_personas'),
        ];
    }
} catch (Throwable $e) { $err('agenda', $e); }


// 8. Demanda por día de la semana (12 meses) y días pedidos sin habilitar
try {
    $porDia = DB::table('reservas')->where('fecha_visita', '>=', $desde)->where('fecha_visita', '<', now()->toDateString())
        ->groupBy('fecha_visita')->selectRaw('fecha_visita f, SUM(cantidad_personas) p, COUNT(*) n')->get();
    $dow = [];
    foreach ($porDia as $x) {
        $k = Carbon::parse($x->f)->dayOfWeekIso;
        $dow[$k]['dias'] = ($dow[$k]['dias'] ?? 0) + 1; $dow[$k]['pers'] = ($dow[$k]['pers'] ?? 0) + (int) $x->p;
        $dow[$k]['max'] = max($dow[$k]['max'] ?? 0, (int) $x->p);
    }
    ksort($dow);
    $out['por_dia_semana'] = $dow;
    $out['top_dias'] = $porDia->sortByDesc('p')->take(10)->values();
    foreach (['bot_dias_interes', 'dias_interes', 'bot_interes_fechas'] as $t) {
        if (Schema::hasTable($t)) { $out['interes_dias'] = ['tabla' => $t, 'filas' => DB::table($t)->count()]; break; }
    }
} catch (Throwable $e) { $err('dias', $e); }

$json = json_encode($out, JSON_UNESCAPED_UNICODE);
file_put_contents(storage_path('app/proyeccion_datos.json'), $json);
echo $json, "\n";
