<?php
// Noviembre 2026: alinea programas, precios, parámetros, reglas del bot y calendario con el afiche.
// Uso: php datos.php            -> muestra qué cambiaría (no cambia nada)
//      php datos.php --aplicar  -> aplica y sincroniza la web (WooCommerce)
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
$aplicar = in_array('--aplicar', $argv, true);
$f = function ($n) { return '$'.number_format($n, 0, ',', '.'); };
echo $aplicar ? "== APLICANDO ==\n" : "== VISTA PREVIA (no se cambia nada) ==\n";

// 1) Precios = afiche (el recargo de verano ya va incluido en el precio, recargo pasa a 0)
$precios = [39 => 50000, 1 => 55000, 28 => 75000, 48 => 85000, 2 => 110000,   // regulares
            45 => 45000, 43 => 49500, 49 => 67500, 3 => 76500];               // S = 10% menos
$sync = [];
foreach ($precios as $id => $v) {
    $p = App\Programa::find($id);
    if (!$p) { echo "  ! programa #$id no existe\n"; continue; }
    if ((int) $p->valor_programa !== $v) {
        echo "  #$id {$p->nombre_programa}: ".$f($p->valor_programa)." -> ".$f($v)."\n";
        if ($aplicar) { $p->valor_programa = $v; $p->save(); }
        $sync[] = $id;
    } else echo "  #$id {$p->nombre_programa}: ".$f($v)." ok\n";
}
// 2) Botacura Full: masaje 60 min + espumante, disponible para el bot
$full = App\Programa::with('servicios')->find(2);
$ids = $full->servicios->pluck('id')->all();
$nuevos = array_values(array_unique(array_merge(array_diff($ids, [1]), [23, 18])));
sort($ids); sort($nuevos);
if ($ids != $nuevos) {
    echo "  Botacura Full servicios: ".$full->servicios->pluck('nombre_servicio')->implode(', ')." -> ".App\Servicio::whereIn('id', $nuevos)->pluck('nombre_servicio')->implode(', ')."\n";
    if ($aplicar) { $full->servicios()->sync($nuevos); }
    $sync[] = 2;
}
// 3) Parámetros del bot
$params = ['param_recargo_verano' => '0', 'param_programas_bot' => '1,28,39,48,2,3,43,45,49', 'param_min_personas_lun_jue' => '10'];
foreach ($params as $k => $v) {
    $row = DB::table('bot_secciones_texto')->where('clave', $k)->first();
    $act = $row ? $row->contenido : '(no existe)';
    if ((string) $act !== $v) {
        echo "  $k: $act -> $v\n";
        if ($aplicar) {
            if ($row) DB::table('bot_secciones_texto')->where('clave', $k)->update(['contenido' => $v, 'updated_at' => now()]);
            else DB::table('bot_secciones_texto')->insert(['clave' => $k, 'categoria' => 'parametros', 'contenido' => $v,
                'etiqueta' => 'Mínimo de personas por reserva de lunes a jueves (feriados excluidos)',
                'orden' => (int) DB::table('bot_secciones_texto')->where('categoria', 'parametros')->max('orden') + 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
// 4) Reglas del bot
$reglas = [
 'regla_temporada_verano' => 'TEMPORADA DE VERANO (noviembre a febrero): los valores de la lista YA son los de temporada (incluyen la piscina). No sumes recargos: la acción "cotizar" entrega el valor final.',
 'regla_programas_semana' => 'LUNES A JUEVES: atendemos de lunes a domingo, de 10:00 a 19:00 horas, solo con reserva. De lunes a jueves solo se reciben reservas de 10 o más personas, y en noviembre todos los programas tienen 10% de descuento esos días (son los programas que terminan en "S": su precio ya incluye el descuento, nunca lo descuentes de nuevo; solo pago por transferencia y no son reagendables). El viernes, sábado, domingo y feriados se usa el precio normal.
- Si una pareja o un grupo de menos de 10 personas pide un día de lunes a jueves, explícale con amabilidad que esos días son para grupos de 10 o más y ofrécele viernes, sábado o domingo.
- Si es un grupo de 10 o más, sigue la regla de EVENTOS Y GRUPOS (cotización por correo) y cuéntale que de lunes a jueves tiene 10% de descuento.',
 'regla_eventos' => 'EVENTOS, EMPRESAS Y GRUPOS DE 10 O MÁS PERSONAS:
- Si preguntan por un evento, una actividad de empresa o institución, o un grupo de 10 o más personas, NO cotices por WhatsApp. Pídele amablemente que envíe un correo a hola@botacura.cl solicitando la cotización, indicando: nombre de la empresa u organización, fecha tentativa, cantidad de personas, programa de interés y si necesita factura. Dile que le responderemos por correo con la cotización.
- Si preguntan por cambios: los grupos de más de 15 personas no pueden modificar la reserva con 72 horas; los cambios de fecha o de cantidad de personas se piden con al menos 10 días de anticipación.',
 'regla_notas' => 'PROGRAMAS DE NOVIEMBRE (valores por persona, iguales al afiche): Wellness Day $50.000 (sauna 15 min, tinaja 45 min, almuerzo a la carta); Wellness Plus $55.000 (lo mismo + desayuno buffet u once buffet); Relax Day con masaje $75.000 (sauna, tinaja, masaje relajante 30 min, almuerzo); Full Day $85.000 (sauna, tinaja, masaje relajante 30 min, desayuno u once buffet, almuerzo); Botacura Full $110.000 (sauna, tinaja, masaje relajante 60 min, desayuno u once buffet, almuerzo y espumante de 375 ml por pareja).
Todos incluyen casilleros, estacionamiento, duchas y camarines.
Los masajes adicionales NO tienen descuento aunque ya tengas programa con masaje.',
];
foreach ($reglas as $k => $v) {
    $act = (string) DB::table('bot_secciones_texto')->where('clave', $k)->value('contenido');
    if (trim($act) !== trim($v)) {
        echo "  regla $k: se reemplaza (".mb_strlen($act)." -> ".mb_strlen($v)." caracteres)\n";
        if ($aplicar) DB::table('bot_secciones_texto')->where('clave', $k)->update(['contenido' => $v, 'updated_at' => now()]);
    }
}
// 5) Textos del recinto/perfil que dicen "jueves a domingo"
foreach (DB::table('bot_secciones_texto')->whereIn('categoria', ['perfil', 'recinto', 'reglas'])->get() as $r) {
    $n = preg_replace(['/jueves a domingo y festivos/iu', '/jueves a domingo/iu'], ['lunes a domingo', 'lunes a domingo'], $r->contenido);
    if ($n !== $r->contenido) {
        echo "  texto '{$r->clave}': 'jueves a domingo' -> 'lunes a domingo'\n";
        if ($aplicar) DB::table('bot_secciones_texto')->where('id', $r->id)->update(['contenido' => $n, 'updated_at' => now()]);
    }
}
// 6) Calendario: habilitar lunes a miércoles de noviembre (desde mañana)
$d = Carbon::tomorrow('America/Santiago')->max(Carbon::create(2026, 11, 1)); $fin = Carbon::create(2026, 11, 30);
$hab = 0;
for (; $d->lte($fin); $d->addDay()) {
    if ($d->dayOfWeekIso > 3) continue;
    $row = App\FechaDisponible::whereDate('fecha', $d->toDateString())->first();
    if ($row && $row->habilitada) continue;
    $hab++;
    if ($aplicar) {
        if ($row) { $row->habilitada = true; $row->save(); }
        else App\FechaDisponible::create(['fecha' => $d->toDateString(), 'tipo' => 'regular', 'habilitada' => true, 'nota' => 'Lunes a miércoles: grupos de 10+']);
    }
}
echo "  Calendario: $hab días de lunes a miércoles de noviembre ".($aplicar ? 'habilitados' : 'por habilitar')."\n";
App\Services\BotTarifasService::limpiarCache();
// 7) Web (WooCommerce): precio, nombre y descripción desde la app
if ($aplicar && $sync) {
    $wc = app(App\Services\WooCommerceService::class);
    foreach (array_unique($sync) as $id) {
        $p = App\Programa::find($id);
        if (!$p->wc_product_id || $p->solo_plataforma) { echo "  web: #$id {$p->nombre_programa} no está en la web\n"; continue; }
        try { $wc->updateProduct($p); echo "  web: #$id {$p->nombre_programa} -> ".$f($p->valor_programa)." ✓\n"; }
        catch (\Throwable $e) { echo "  web: #$id ERROR ".substr($e->getMessage(), 0, 150)."\n"; }
    }
}
echo $aplicar ? "LISTO\n" : "\nPara aplicar: bash -s -- --aplicar\n";
