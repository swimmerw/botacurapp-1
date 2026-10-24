<?php
// Agrega los parámetros de temporada baja y la nota de octubre en la regla del bot.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$def = App\Services\BotTarifasService::parametrosDefault();
foreach (['param_meses_temporada_baja', 'param_precios_temporada_baja'] as $k) {
    if (!DB::table('bot_secciones_texto')->where('clave', $k)->exists()) {
        DB::table('bot_secciones_texto')->insert(['clave' => $k, 'categoria' => 'parametros', 'etiqueta' => $def[$k][0], 'contenido' => $def[$k][1],
            'orden' => (int) DB::table('bot_secciones_texto')->where('categoria', 'parametros')->max('orden') + 1, 'created_at' => now(), 'updated_at' => now()]);
        echo "  parámetro $k = {$def[$k][1]}\n";
    }
}
$nota = "\n- HASTA EL 31 DE OCTUBRE rigen las condiciones de octubre: se atiende de jueves a domingo y festivos, los programas S son solo jueves y viernes (sin mínimo de personas) y los valores son los de octubre: Wellness Day \$45.000, Wellness Plus \$50.000, Relax Day \$70.000, Full Day \$80.000 y Botacura Full \$95.000. La acción \"cotizar\" ya entrega el valor correcto según la fecha.";
$r = DB::table('bot_secciones_texto')->where('clave', 'regla_programas_semana')->first();
if ($r && strpos($r->contenido, 'HASTA EL 31 DE OCTUBRE') === false) {
    DB::table('bot_secciones_texto')->where('id', $r->id)->update(['contenido' => $r->contenido . $nota, 'updated_at' => now()]);
    echo "  regla del bot: agregada la nota de octubre\n";
}
App\Services\BotTarifasService::limpiarCache();
foreach (['2026-10-25' => 39, '2026-10-29' => 45, '2026-11-01' => 39, '2026-11-05' => 45] as $f => $id) {
    $c = App\Services\BotTarifasService::cotizar(['programa_id' => $id, 'fecha' => $f, 'personas' => 2]);
    echo "  prueba $f programa #$id x2: ".($c['valor_persona_formato'] ?? json_encode($c['valor_persona'] ?? null))." · ".($c['ok'] ? 'ok' : implode(' / ', array_column($c['errores'], 'mensaje')))."\n";
}
