<?php
// Solo lectura: costo real de la comida por persona (ene–sep 2026) desde egresos, facturas con detalle y lo servido.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$f = function ($n) { return '$' . number_format((int) round($n), 0, ',', '.'); };
$desde = '2026-01-01'; $hasta = '2026-09-30';

// 1. Lo comprado por categoría y proveedor (panel de egresos: facturas SII + pagos del banco clasificados)
$ep = app(App\Http\Controllers\EgresosPanelController::class);
$m = new ReflectionMethod($ep, 'datos'); $m->setAccessible(true);
$cats = []; $prov = [];
for ($c = Carbon\Carbon::parse($desde); $c->lte(Carbon\Carbon::parse($hasta)); $c->addMonth()) {
    $d = $m->invoke($ep, $c->copy()->startOfMonth(), $c->copy()->endOfMonth(), true);
    foreach ($d['arbol'] as $cat => $x) foreach ($x['subs'] as $sub => $s) {
        if (!in_array($cat, ['Insumos'], true)) continue;
        $cats[$sub][$c->format('m')] = ($cats[$sub][$c->format('m')] ?? 0) + $s['total'];
        foreach ($s['quien'] as $q => $v) $prov["$sub|$q"] = ($prov["$sub|$q"] ?? 0) + $v;
    }
}
echo "== Insumos comprados ene–sep 2026 (con IVA)\n";
foreach ($cats as $sub => $ms) echo "  $sub: " . $f(array_sum($ms)) . ' · por mes ' . implode(' ', array_map(function ($k, $v) use ($f) { return "$k:" . round($v / 1e6, 2); }, array_keys($ms), $ms)) . "\n";
arsort($prov);
echo "== Principales proveedores de insumos\n";
foreach (array_slice($prov, 0, 30, true) as $k => $v) echo "  $k: " . $f($v) . "\n";

// Verduras de Susana pagadas fuera de egresos (deuda y avisos del banco)
if (Schema::hasTable('fin_correos')) {
    $sus = DB::table('fin_correos')->whereIn('tipo', ['pago', 'personal'])->whereBetween('fecha', [$desde, $hasta])
        ->where(function ($q) { $q->where('nombre', 'like', '%olivares%')->orWhere('rut', 'like', '9.355.184%')->orWhere('rut', 'like', '9355184%'); })->sum('monto');
    echo "Pagos a Susana Olivares en avisos del banco: " . $f($sus) . "\n";
}

// 2. Detalle de facturas (compra_items) agrupado por tipo de producto
if (Schema::hasTable('compra_items')) {
    $cols = Schema::getColumnListing('compra_items');
    echo "== compra_items columnas: " . implode(',', $cols) . "\n";
    $monto = collect(['monto_item', 'monto', 'total', 'subtotal', 'valor', 'monto_neto'])->first(function ($c) use ($cols) { return in_array($c, $cols, true); });
    $link = collect(['egreso_id', 'id_egreso'])->first(function ($c) use ($cols) { return in_array($c, $cols, true); });
    if ($monto && $link) {
        $grupos = [
            'Carnes y pescados' => 'carne|vacun|cerdo|pollo|pavo|filete|lomo|posta|asado|costill|pechug|trutro|longaniz|chorizo|salmon|salmón|pescad|reineta|merluza|camar|marisc|hamburg|molida|tocino|jamon pierna|abastero|punta',
            'Verduras y frutas' => 'lechug|tomate|papa|cebolla|zanahor|palta|limon|limón|pepino|zapall|choclo|fruta|platan|plátan|manzana|naranja|frutill|piña|pina|mango|uva|kiwi|pimient|ajo|champi|espinaca|brocol|apio|cilantro|perejil|albahaca|menta|betarraga|repollo|berenjena|frambuesa|arandano|maracuya',
            'Panadería, lácteos y huevos' => 'pan |pan$|marraqueta|hallulla|croissant|queque|torta|galleta|mermelada|manjar|mantequilla|margarina|queso|leche|yogur|cereal|avena|granola|huevo|crema|kuchen|pastel',
            'Café, té y desayuno' => 'cafe|café|te |infusion|infusión|azucar|azúcar|endulzante|miel|jugo|chocolate',
            'Abarrotes y salsas' => 'arroz|fideo|pasta|aceite|harina|sal |salsa|vinagre|lenteja|garbanzo|poroto|especia|oregano|comino|caldo|mayonesa|ketchup|mostaza|conserva|atun|atún|aceituna',
            'Bebidas y bar' => 'bebida|coca|sprite|fanta|agua|cerveza|vino|pisco|ron |gin|vodka|tequila|aperol|ramazzot|espumante|licor|hielo|tonica|tónica|ginger|limonada|cachaca|whisk',
            'Desechables y aseo' => 'servillet|vaso|desechable|bolsa|detergente|cloro|papel|guante|film|alusa|toalla|lavaloza|esponja|basura',
        ];
        $items = DB::table('compra_items as i')->join('egresos as e', 'e.id', '=', "i.$link")->whereBetween('e.fecha_egreso', [$desde, $hasta])
            ->get(['i.nombre', "i.$monto as m"]);
        $res = []; $otros = [];
        foreach ($items as $it) {
            $n = ' ' . mb_strtolower($it->nombre) . ' '; $g = 'Otros';
            foreach ($grupos as $k => $re) if (preg_match('/(' . $re . ')/u', $n)) { $g = $k; break; }
            $res[$g] = ($res[$g] ?? 0) + (int) $it->m;
            if ($g === 'Otros') $otros[trim($n)] = ($otros[trim($n)] ?? 0) + (int) $it->m;
        }
        arsort($res); arsort($otros);
        echo "== Detalle de facturas ene–sep por tipo (columna $monto, " . count($items) . " líneas)\n";
        foreach ($res as $k => $v) echo "  $k: " . $f($v) . "\n";
        echo "  Otros más grandes: " . implode(' · ', array_map(function ($k, $v) use ($f) { return mb_substr($k, 0, 28) . ' ' . $f($v); }, array_keys(array_slice($otros, 0, 12, true)), array_slice($otros, 0, 12, true))) . "\n";
    } else echo "  (no encontré columna de monto o de egreso)\n";
}

// 3. Lo servido: personas, almuerzos y desayunos/onces según lo que incluye cada programa + extras
$sv = DB::table('programa_servicio as ps')->join('servicios as s', 's.id', '=', 'ps.id_servicio')->get(['ps.id_programa', 's.nombre_servicio'])
    ->groupBy('id_programa')->map(function ($g) { return $g->pluck('nombre_servicio')->map(function ($x) { return mb_strtolower($x); })->all(); });
$res = DB::table('reservas')->whereBetween('fecha_visita', [$desde, $hasta])->get(['id_programa', 'cantidad_personas']);
$alm = 0; $dyo = 0; $pers = 0; $sinAlm = 0;
foreach ($res as $r) {
    $s = $sv[$r->id_programa] ?? []; $p = (int) $r->cantidad_personas; $pers += $p;
    if (in_array('almuerzo', $s, true)) $alm += $p; else $sinAlm += $p;
    if (in_array('desayuno y once', $s, true)) $dyo += 2 * $p; elseif (in_array('desayuno u once', $s, true)) $dyo += $p;
}
$extra = Schema::hasTable('detalle_servicios_extra') ? (int) DB::table('detalle_servicios_extra as d')->join('servicios as s', 's.id', '=', 'd.id_servicio_extra')
    ->join('consumos as c', 'c.id', '=', 'd.id_consumo')->whereBetween('c.created_at', [$desde, $hasta . ' 23:59:59'])
    ->where('s.nombre_servicio', 'like', '%desayuno%')->sum('d.cantidad_servicio') : 0;
$extraAlm = Schema::hasTable('detalle_servicios_extra') ? (int) DB::table('detalle_servicios_extra as d')->join('servicios as s', 's.id', '=', 'd.id_servicio_extra')
    ->join('consumos as c', 'c.id', '=', 'd.id_consumo')->whereBetween('c.created_at', [$desde, $hasta . ' 23:59:59'])
    ->where('s.nombre_servicio', 'like', '%almuerzo%')->sum('d.cantidad_servicio') : 0;
echo "== Servido ene–sep 2026\n  Personas: $pers · almuerzos por programa: $alm (+ $extraAlm extra) · sin almuerzo: $sinAlm · desayunos u onces por programa: $dyo (+ $extra extra)\n";
$cons = DB::table('detalles_consumos as d')->join('consumos as c', 'c.id', '=', 'd.id_consumo')->join('productos as p', 'p.id', '=', 'd.id_producto')
    ->leftJoin('tipos_productos as t', 't.id', '=', 'p.id_tipo_producto')->whereBetween('c.created_at', [$desde, $hasta . ' 23:59:59'])
    ->groupBy('t.nombre')->selectRaw('t.nombre tipo, SUM(d.subtotal) v')->pluck('v', 'tipo');
echo "== Ventas de carta ene–sep por tipo: " . collect($cons)->map(function ($v, $k) use ($f) { return "$k " . $f($v); })->implode(' · ') . "\n";
