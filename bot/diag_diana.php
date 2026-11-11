<?php
// Solo lectura: qué pasó con la reserva y el comprobante de un cliente (11/11/2026). Uso: php diag.php 56932133925
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$tel = preg_replace('/\D/', '', $argv[1] ?? '56932133925');
$corto = substr($tel, -9);
$tels = [$tel, $corto, '56' . $corto];
function t($l, $fn) { echo "\n### $l\n"; try { $fn(); } catch (\Throwable $e) { echo '(no se pudo: ' . mb_substr($e->getMessage(), 0, 160) . ")\n"; } }
function fila($r) { echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n"; }
t('cupos apartados (holds)', function () use ($tels, $tel) {
    foreach (DB::table('reserva_holds')->where(function ($w) use ($tels, $tel) { $w->whereIn('telefono', $tels)->orWhere('datos_json', 'like', '%' . $tel . '%'); })->orderBy('id')->get() as $h) {
        unset($h->datos_json); fila($h);
    }
});
t('comprobantes en revisión', function () use ($tels) {
    foreach (DB::table('comprobantes_revision')->whereIn('telefono', $tels)->orderBy('id')->get() as $c) { fila($c); }
});
t('cliente y reservas', function () use ($tels) {
    $cl = DB::table('clientes')->whereIn('whatsapp_cliente', $tels)->get();
    foreach ($cl as $c) { echo "cliente #{$c->id} {$c->nombre_cliente} creado {$c->created_at}\n"; }
    foreach (DB::table('reservas as r')->leftJoin('ventas as v', 'v.id_reserva', '=', 'r.id')->leftJoin('programas as p', 'p.id', '=', 'r.id_programa')
        ->whereIn('r.cliente_id', $cl->pluck('id')->all() ?: [0])->orderBy('r.id')
        ->select('r.id', 'r.fecha_visita', 'r.cantidad_personas', 'p.nombre_programa', 'r.estado', 'r.created_at', 'v.id as venta', 'v.estado_pago', 'v.abono_programa')->get() as $r) { fila($r); }
});
t('reservas creadas ayer 15:40–16:10 (por si quedó con otro cliente)', function () {
    foreach (DB::table('reservas as r')->leftJoin('clientes as c', 'c.id', '=', 'r.cliente_id')->whereBetween('r.created_at', ['2026-11-10 15:40:00', '2026-11-10 16:10:00'])
        ->select('r.id', 'r.fecha_visita', 'r.cantidad_personas', 'c.nombre_cliente', 'c.whatsapp_cliente', 'r.created_at')->get() as $r) { fila($r); }
});
t('eventos del bot', function () use ($tels) {
    foreach (DB::table('bot_eventos')->whereIn('telefono', $tels)->where('created_at', '>=', '2026-11-05')->orderBy('id')->get() as $e) {
        echo $e->created_at . ' ' . $e->tipo . ' ' . mb_substr((string) ($e->datos_json ?? $e->datos ?? ''), 0, 300) . "\n";
    }
});
t('conversación (últimos 14 turnos, recortados)', function () use ($tels) {
    $c = DB::table('bot_conversaciones')->whereIn('usuario_id', $tels)->orderBy('id', 'desc')->first();
    if (!$c) { echo "sin conversación\n"; return; }
    echo "requiere_humano={$c->requiere_humano} humano_hasta={$c->humano_hasta}\n";
    echo "conversación actualizada: {$c->updated_at}\n";
    foreach (array_slice(json_decode($c->historial_json ?: '[]', true) ?: [], -24) as $m) {
        echo strtoupper($m['role']) . ': ' . mb_substr(str_replace("\n", ' ', (string) $m['content']), 0, 1500) . "\n";
    }
});
t('log de ayer y hoy', function () use ($tel, $corto) {
    foreach (glob(storage_path('logs/laravel-2026-11-*.log')) as $f) {
        foreach (file($f) as $l) {
            if (strpos($l, $corto) !== false && preg_match('/omprobante|Hold|Reserva|Vision|ERROR|rechaz|Bot\]/', $l)) { echo mb_substr(trim($l), 0, 500) . "\n"; }
        }
    }
});
t('de dónde sale "Tu reserva ya fue ingresada"', function () {
    echo shell_exec("grep -rln 'reserva ya fue ingresada' " . base_path('app') . ' ' . base_path('resources') . ' 2>/dev/null') ?: "no está en el código (¿n8n o plantilla de Meta?)\n";
});
