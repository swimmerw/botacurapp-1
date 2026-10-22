<?php
// Solo lectura: programas, parámetros del bot y fechas de noviembre, para comparar con el afiche.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$cols = Schema::getColumnListing('programas');
echo "COLUMNAS programas: ".implode(',', $cols)."\n\n";
$bot = App\Services\BotTarifasService::idsProgramasBot();
$sem = App\Services\BotTarifasService::idsProgramasSemana();
foreach (App\Programa::with('servicios')->orderBy('id')->get() as $p) {
    $a = $p->toArray(); unset($a['servicios'], $a['descripcion'], $a['created_at'], $a['updated_at']);
    foreach ($a as $k => $v) if (is_string($v) && strlen($v) > 60) $a[$k] = substr($v, 0, 60).'…';
    echo "#{$p->id} ".($p->estado ?: 'activo?').(in_array($p->id,$bot)?' [BOT]':'').(in_array($p->id,$sem)?' [S]':'')."\n  ".json_encode($a, JSON_UNESCAPED_UNICODE)."\n  servicios: ".$p->servicios->pluck('nombre_servicio')->implode(', ')."\n";
}
echo "\nSERVICIOS:\n";
foreach (App\Servicio::orderBy('id')->get() as $s) echo "  #{$s->id} {$s->nombre_servicio} \${$s->valor_servicio} dur={$s->duracion}\n";
echo "\nPARAMETROS BOT:\n"; foreach (App\Services\BotTarifasService::parametros() as $k=>$v) echo "  $k = ".str_replace("\n",' | ',$v)."\n";
echo "\nREGLAS BOT (texto actual):\n"; foreach (App\Services\BotPromptService::reglasComerciales() as $k=>$v) echo "--- $k\n$v\n";
echo "\nFECHAS NOV-DIC habilitadas por día de semana:\n";
$f = App\FechaDisponible::whereBetween('fecha', ['2026-11-01','2026-12-31'])->get();
foreach ($f->groupBy(function($x){return $x->fecha->format('Y-m');}) as $m=>$g) {
  $c=[]; foreach ($g as $x) { $d=$x->fecha->dayOfWeekIso; $c[$d]=($c[$d]??0)+($x->habilitada?1:0);} ksort($c);
  echo "  $m: ".json_encode($c)." (1=lun..7=dom) total filas ".$g->count()."\n"; }
echo "\nCLAVES bot_secciones_texto: ".DB::table('bot_secciones_texto')->pluck('clave')->implode(', ')."\n";
