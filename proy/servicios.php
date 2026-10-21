<?php
// Solo lectura: programas con sus servicios, valores y costos, y pago a masoterapeutas por masaje.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$f = function ($n) { return $n === null ? '-' : '$' . number_format((int) $n, 0, ',', '.'); };
echo "== Masajes (precio y pago al masoterapeuta)\n";
foreach (DB::table('precios_tipos_masajes as p')->leftJoin('tipos_masajes as t', 't.id', '=', 'p.id_tipo_masaje')->orderBy('t.nombre')->orderBy('p.duracion_minutos')
    ->get(['p.id', 't.nombre', 'p.duracion_minutos', 'p.precio_unitario', 'p.precio_pareja', 'p.pago_masoterapeuta']) as $m) {
    echo "  #{$m->id} {$m->nombre} {$m->duracion_minutos} min · precio {$f($m->precio_unitario)} · pareja {$f($m->precio_pareja)} · masoterapeuta {$f($m->pago_masoterapeuta)}\n";
}
echo "== Servicios\n";
foreach (DB::table('servicios')->orderBy('id')->get() as $s) echo "  #{$s->id} {$s->nombre_servicio}: valor {$f($s->valor_servicio)} · costo {$f($s->costo_servicio)}\n";
echo "== Programas activos y sus servicios\n";
$q = DB::table('programas');
if (Schema::hasColumn('programas', 'estado')) $q->where('estado', 1);
foreach ($q->orderBy('valor_programa')->get() as $p) {
    $sv = DB::table('programa_servicio as ps')->join('servicios as s', 's.id', '=', 'ps.id_servicio')->where('ps.id_programa', $p->id)->pluck('s.nombre_servicio')->implode(', ');
    $n = DB::table('reservas')->where('id_programa', $p->id)->where('fecha_visita', '>=', now()->subYear()->toDateString())->sum('cantidad_personas');
    echo "  #{$p->id} {$p->nombre_programa} {$f($p->valor_programa)}" . (isset($p->espacio_tipo) ? " [{$p->espacio_tipo}]" : '') . " · {$n} personas en 12 meses · incluye: {$sv}\n";
}
