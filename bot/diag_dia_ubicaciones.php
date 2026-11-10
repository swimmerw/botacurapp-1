<?php
// Solo lectura: quién ocupa cada ubicación de un día y qué cupos apartados (holds) hay (10/11/2026).
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
function t($l, $fn) { try { $fn(); } catch (\Throwable $e) { echo $l . ': (no se pudo: ' . mb_substr($e->getMessage(), 0, 150) . ")\n"; } }
$f = $argv[1] ?? '2026-11-28';
echo "===== $f =====\n";
t('ubicaciones', function () use ($f) {
    $ub = DB::table('ubicaciones')->where('activo', true)->orderBy('sub_tipo')->orderBy('id')->get();
    $res = DB::table('reservas as r')->leftJoin('programas as p', 'p.id', '=', 'r.id_programa')->whereRaw('DATE(r.fecha_visita) = ?', [$f])
        ->select('r.id', 'r.estado', 'r.cantidad_personas', 'p.nombre_programa', 'r.created_at')->get()->keyBy('id');
    $occ = [];
    foreach (DB::table('visitas')->whereIn('id_reserva', $res->keys()->all() ?: [0])->whereNotNull('id_ubicacion')->get() as $v) { $occ[$v->id_ubicacion][$v->id_reserva] = 'visita'; }
    foreach (DB::table('reserva_ubicaciones')->whereIn('id_reserva', $res->keys()->all() ?: [0])->get() as $v) { $occ[$v->id_ubicacion][$v->id_reserva] = 'ru:' . ($v->cantidad_personas ?? '?') . 'p'; }
    foreach ($ub as $u) {
        $txt = [];
        foreach ($occ[$u->id] ?? [] as $rid => $src) { $r = $res[$rid] ?? null; $txt[] = "#$rid " . ($r ? "{$r->cantidad_personas}p {$r->nombre_programa} ({$r->estado}, creada {$r->created_at})" : '?') . " [$src]"; }
        echo str_pad("[{$u->id}] " . ($u->nombre ?? '') . " · {$u->sub_tipo} · cap {$u->capacidad_max}", 48) . ' → ' . ($txt ? implode(' | ', $txt) : 'libre') . "\n";
    }
    $sin = $res->keys()->diff(collect($occ)->flatMap(function ($x) { return array_keys($x); })->unique());
    echo "Reservas sin ubicación: " . ($sin->isEmpty() ? 'ninguna' : $sin->map(function ($id) use ($res) { $r = $res[$id]; return "#$id {$r->cantidad_personas}p {$r->nombre_programa} ({$r->estado})"; })->implode(', ')) . "\n";
});
t('holds', function () use ($f) {
    $hs = DB::table('reserva_holds')->where('fecha', $f)->orderBy('id')->get();
    echo "\nCupos apartados por el bot (holds) para el día: " . $hs->count() . "\n";
    foreach ($hs as $h) {
        $d = json_decode($h->datos_json ?: '{}', true) ?: [];
        $p = DB::table('programas')->where('id', $h->programa_id)->value('nombre_programa');
        echo "  hold #{$h->id} {$h->personas}p {$p} estado={$h->estado} expira={$h->expira_en} creado={$h->created_at}"
            . (isset($h->reserva_id) ? " reserva=" . ($h->reserva_id ?? '-') : '') . " ubicación pedida=" . ($d['ubicacion_preferida'] ?? '-') . "\n";
    }
});
$svc = app(\App\Services\DisponibilidadService::class);
echo "\nslotsTinaja: " . json_encode($svc->slotsTinaja($f, 0)) . "\ncapacidadLibre: " . json_encode($svc->capacidadLibre($f)) . "\n";
t('plan', function () use ($f) {
    $pid = DB::table('programas')->where('nombre_programa', 'Relax Day')->value('id');
    echo "Plan del bot hoy para Relax Day 5p: " . json_encode(\App\Services\BotUbicacionesService::plan($f, (int) $pid, 5, null), JSON_UNESCAPED_UNICODE) . "\n";
});
