<?php
// Usuarios activos y si están habilitados para el CSV de transferencias (datos bancarios completos), con sus correos.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$bancos = config('bancos.bancos', []);
$tipos = array_keys(config('bancos.tipos_cuenta_destino', []));
$q = DB::table('users')->orderBy('name');
if (Schema::hasColumn('users', 'activo')) {
    $q->where('activo', true);
}
$hab = []; $no = [];
foreach ($q->get() as $u) {
    $falta = [];
    if (empty($u->rut)) $falta[] = 'RUT';
    if (empty($u->banco) || !isset($bancos[$u->banco])) $falta[] = 'banco';
    if (empty($u->tipo_cuenta_bancaria) || !in_array($u->tipo_cuenta_bancaria, $tipos)) $falta[] = 'tipo de cuenta';
    if (empty($u->numero_cuenta_bancaria)) $falta[] = 'n° de cuenta';
    $ult = Schema::hasTable('sueldos') ? DB::table('sueldos')->where('id_user', $u->id)->max('dia_trabajado') : null;
    $fila = [
        'nombre' => $u->name,
        'correo_aviso' => $u->correo_personal ?? '',
        'correo_app' => $u->email ?? '',
        'banco' => $bancos[$u->banco] ?? ($u->banco ?: ''),
        'cuenta' => trim(($u->tipo_cuenta_bancaria ?? '') . ' ' . ($u->numero_cuenta_bancaria ?? '')),
        'ultimo_dia' => $ult ? date('d-m-Y', strtotime($ult)) : 'nunca',
        'falta' => $falta,
    ];
    if ($falta) $no[] = $fila; else $hab[] = $fila;
}
echo "HABILITADOS PARA TRANSFERIR (" . count($hab) . ")\n";
echo "Nombre | Correo de aviso (va en el CSV) | Correo de la app | Banco | Cuenta | Último día trabajado\n";
foreach ($hab as $f) {
    echo "{$f['nombre']} | " . ($f['correo_aviso'] ?: 'SIN CORREO DE AVISO') . " | {$f['correo_app']} | {$f['banco']} | {$f['cuenta']} | {$f['ultimo_dia']}\n";
}
echo "\nNO HABILITADOS: les faltan datos (" . count($no) . ")\n";
echo "Nombre | Falta | Correo de aviso | Correo de la app | Último día trabajado\n";
foreach ($no as $f) {
    echo "{$f['nombre']} | " . implode(', ', $f['falta']) . " | " . ($f['correo_aviso'] ?: '-') . " | {$f['correo_app']} | {$f['ultimo_dia']}\n";
}
