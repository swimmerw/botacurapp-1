<?php
// Solo lectura (10/11/2026): por qué el bot dijo que había cupo. Uso: php diag_cupo.php 56990908493 2026-11-14 2
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$tel = $argv[1]; $fecha = $argv[2]; $pers = (int) ($argv[3] ?? 2);

echo "==== Lo que el sistema le respondió al bot en ese chat ====\n";
$c = DB::table('bot_conversaciones')->where('usuario_id', $tel)->orderBy('id', 'desc')->first();
foreach (json_decode($c->historial_json ?? '[]', true) ?: [] as $t) {
    if (preg_match_all('/\[Sistema-(disponibilidad|ubicaciones|cotizacion|reserva)[^\]]*\]/u', (string) $t['content'], $m)) {
        foreach ($m[0] as $x) { echo '- ' . mb_substr($x, 0, 700) . "\n"; }
    }
    if ($t['role'] === 'assistant' && preg_match('/cupo|disponib/iu', $t['content'])) { echo '  BOT: ' . mb_substr(str_replace("\n", ' ', $t['content']), 0, 200) . "\n"; }
}
echo "\n==== Reservas del {$fecha} en la app (sin canceladas) ====\n";
foreach (DB::table('reservas as r')->leftJoin('programas as p', 'p.id', '=', 'r.id_programa')->where('r.fecha_visita', $fecha)
    ->whereNotIn('r.estado', ['cancelada', 'no_show'])->groupBy('p.nombre_programa', 'p.espacio_tipo')
    ->select('p.nombre_programa', 'p.espacio_tipo', DB::raw('COUNT(*) n'), DB::raw('SUM(r.cantidad_personas) pers'))->get() as $r) {
    printf("  %-32s espacio=%-12s reservas=%d personas=%d\n", $r->nombre_programa, $r->espacio_tipo, $r->n, $r->pers);
}
echo "  Cupos apartados activos: " . DB::table('reserva_holds')->where('fecha', $fecha)->where('estado', 'activo')->count() . "\n";
echo "\n==== Lo que calcula el sistema hoy para {$pers} personas ====\n";
foreach (DB::table('programas')->where('nombre_programa', 'like', '%ellness%')->get() as $p) {
    $d = app(\App\Services\DisponibilidadService::class)->disponible($fecha, $p->espacio_tipo ?? null, $pers);
    echo "  #{$p->id} {$p->nombre_programa} (espacio {$p->espacio_tipo}): " . ($d['disponible'] ? 'DISPONIBLE' : 'SIN CUPO') . ' · ' . ($d['razon'] ?? '') . ' · ' . json_encode($d['espacio'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
    if (class_exists('\App\Services\BotUbicacionesService')) {
        $u = \App\Services\BotUbicacionesService::plan($fecha, (int) $p->id, $pers, null);
        echo '     ubicaciones: ' . mb_substr(json_encode($u, JSON_UNESCAPED_UNICODE), 0, 400) . "\n";
    }
}
if (class_exists('\App\Services\BotTarifasService')) {
    $p = DB::table('programas')->where('nombre_programa', 'like', '%ellness%Plus%')->first();
    if ($p) { echo '  reglas: ' . mb_substr(json_encode(\App\Services\BotTarifasService::cotizar(['programa_id' => $p->id, 'fecha' => $fecha, 'personas' => $pers, 'masajes_extra' => 0, 'desayuno_once' => 0, 'tipo_pago' => 'Transferencia']), JSON_UNESCAPED_UNICODE), 0, 400) . "\n"; }
}
echo "\n(Los archivos de disponibilidad quedan en /tmp/diag_servicios para revisarlos si hace falta)\n";
@mkdir('/tmp/diag_servicios');
foreach (['DisponibilidadService', 'BotUbicacionesService'] as $s) { @copy("/var/www/html/botacurapp/app/Services/{$s}.php", "/tmp/diag_servicios/{$s}.php"); }
