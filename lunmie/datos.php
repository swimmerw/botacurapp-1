<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$def = App\Services\BotTarifasService::parametrosDefault();
foreach (['param_dias_solo_grupos'] as $k) if (!DB::table('bot_secciones_texto')->where('clave', $k)->exists())
    DB::table('bot_secciones_texto')->insert(['clave' => $k, 'categoria' => 'parametros', 'etiqueta' => $def[$k][0], 'contenido' => $def[$k][1],
        'orden' => (int) DB::table('bot_secciones_texto')->where('categoria', 'parametros')->max('orden') + 1, 'created_at' => now(), 'updated_at' => now()]);
DB::table('bot_secciones_texto')->where('clave', 'param_min_personas_lun_jue')->update(['etiqueta' => $def['param_min_personas_lun_jue'][0]]);
$r = DB::table('bot_secciones_texto')->where('clave', 'regla_programas_semana')->first();
if ($r) {
    $n = str_replace(
        ["De lunes a jueves solo se reciben reservas de 10 o más personas, y en noviembre",
         "- Si una pareja o un grupo de menos de 10 personas pide un día de lunes a jueves, explícale con amabilidad que esos días son para grupos de 10 o más y ofrécele viernes, sábado o domingo."],
        ["De lunes a miércoles el día se abre solo cuando hay un grupo de 10 o más personas; si ese día ya tiene el grupo reservado, se aceptan parejas y grupos chicos (lo dice la lista de fechas y lo valida \"cotizar\"). En noviembre",
         "- Si una pareja o un grupo de menos de 10 personas pide un lunes, martes o miércoles que aún no tiene grupo, explícale con amabilidad que esos días abren con grupos desde 10 personas y ofrécele jueves a domingo; si quiere ese día igual, que nos escriba más cerca de la fecha. El jueves se atiende a todos, con el 10% de descuento."],
        $r->contenido);
    if ($n !== $r->contenido) { DB::table('bot_secciones_texto')->where('id', $r->id)->update(['contenido' => $n, 'updated_at' => now()]); echo "  regla del bot actualizada\n"; }
}
App\Services\BotTarifasService::limpiarCache();
foreach (['2026-11-02', '2026-11-04', '2026-11-05', '2026-11-07'] as $f) {
    $c = Carbon\Carbon::parse($f);
    echo "  $f (".$c->locale('es')->isoFormat('dddd')."): reservados ".App\Services\BotTarifasService::personasDelDia($f)." · pareja ".(App\Services\BotTarifasService::permiteReservaChica($c, 2) ? 'SÍ' : 'NO')." · grupo de 12 ".(App\Services\BotTarifasService::permiteReservaChica($c, 12) ? 'SÍ' : 'NO')."\n";
}
