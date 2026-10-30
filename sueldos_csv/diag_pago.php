<?php
// Diagnóstico de un pago de sueldo que no se marcó: php diag_pago.php "Elohim"
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$nombre = $argv[1] ?? 'Elohim';
$m = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); };
$us = DB::table('users')->where('name', 'like', '%' . $nombre . '%')->get();
if ($us->count() !== 1) {
    echo "Usuarios con '$nombre': " . ($us->count() ? $us->pluck('name')->implode(', ') : 'ninguno') . "\n";
    if (!$us->count()) exit;
}
foreach ($us as $u) {
    echo "\n### {$u->name} (id {$u->id}) · RUT " . ($u->rut ?: 'SIN RUT') . " · cuenta " . ($u->numero_cuenta_bancaria ?: 'SIN CUENTA') . "\n";

    echo "-- Semanas (últimas 6) según Remuneraciones:\n";
    foreach (app(App\Finanzas\SueldoSemanas::class)->pendientes((int) $u->id, date('Y-m-d')) as $s) {
        $bono = (int) DB::table('sueldos_pagados')->where('user_id', $u->id)->where('semana_inicio', $s['inicio'])->where('semana_fin', $s['fin'])->sum('bono');
        echo "   {$s['etiqueta']}: " . $m($s['total']) . ($bono ? ' + bono ' . $m($bono) : '') . ' · ' . ($s['pagado'] ? 'PAGADO' : 'sin pagar') . "\n";
    }

    if (Schema::hasTable('sueldo_csv_lineas')) {
        echo "-- Líneas en CSV descargados (20 días):\n";
        foreach (DB::table('sueldo_csv_lineas')->where('user_id', $u->id)->where('created_at', '>=', now()->subDays(20))->orderBy('id')->get() as $l) {
            echo "   {$l->created_at} semana {$l->semana_inicio}→{$l->semana_fin} " . $m($l->monto)
                . ($l->pagado_at ? " · pagada {$l->pagado_at}" : ($l->reemplazada ? ' · reemplazada' : ' · pendiente')) . "\n";
        }
    }

    echo "-- Avisos del banco (7 días) que lo nombran o van a su RUT/cuenta:\n";
    $partes = array_filter(explode(' ', $u->name), function ($p) { return mb_strlen($p) >= 4; });
    $rut = $u->rut ? preg_replace('/[^0-9kK]/', '', $u->rut) : null;
    $cs = DB::table('fin_correos')->where('recibido_at', '>=', now()->subDays(7))
        ->where(function ($q) use ($partes, $u) {
            foreach ($partes as $p) { $q->orWhere('nombre', 'like', '%' . $p . '%'); }
            if ($u->numero_cuenta_bancaria) { $q->orWhere('cuenta', ltrim(preg_replace('/\D/', '', $u->numero_cuenta_bancaria), '0')); }
            if ($u->rut) { $q->orWhere('rut', 'like', '%' . substr(preg_replace('/\D/', '', $u->rut), 0, 7) . '%'); }
        })->orderBy('id')->get();
    if ($cs->isEmpty()) {
        echo "   NINGUNO: el aviso de esta transferencia no ha llegado a botacura.info o no se leyó todavía.\n";
    }
    foreach ($cs as $c) {
        echo "   #{$c->id} {$c->fecha} {$c->tipo} " . $m($c->monto) . " · {$c->nombre} · rut {$c->rut} · cuenta {$c->cuenta} · estado {$c->estado}"
            . ($c->nota ? " · {$c->nota}" : '') . ($c->comentario ? " · comentario: {$c->comentario}" : '') . "\n";
    }
}
echo "\n-- Últimos 8 avisos de salida (para ver si llegó con otro nombre):\n";
foreach (DB::table('fin_correos')->whereIn('tipo', ['pago', 'compra', 'personal'])->orderByDesc('id')->limit(8)->get() as $c) {
    echo "   #{$c->id} {$c->fecha} {$c->tipo} " . $m($c->monto) . " · {$c->nombre} · {$c->estado}" . ($c->nota ? " · {$c->nota}" : '') . "\n";
}
