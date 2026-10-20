<?php
// Muestra y confirma los sueldos que ya tienen su aviso de transferencia del banco.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$tiene = Schema::hasColumn('sueldos_pagados', 'confirmado');
echo 'sueldos_pagados ' . ($tiene ? 'tiene' : 'NO tiene') . " la columna confirmado\n";
$v = @file_get_contents('resources/views/themes/backoffice/pages/sueldo/index.blade.php');
echo 'La vista Remuneraciones ' . ($v && strpos($v, 'confirmado') !== false ? 'usa' : 'no usa') . " 'confirmado' para mostrar Pagado\n";
$filas = DB::table('fin_correos as c')->join('sueldos_pagados as s', 's.id', '=', 'c.sueldo_pagado_id')
    ->leftJoin('users as u', 'u.id', '=', 's.user_id')->where('c.estado', 'calzado')
    ->select('c.id as correo', 'c.fecha', 'c.monto', 's.id', 's.semana_inicio', 's.semana_fin', 's.monto as sm', 'u.name' )
    ->addSelect(DB::raw($tiene ? 's.confirmado' : '1 as confirmado'))->orderBy('c.fecha')->get();
$n = 0;
foreach ($filas as $f) {
    $antes = $f->confirmado ? 'ya confirmado' : 'SIN confirmar';
    if (!$f->confirmado) $n += App\Finanzas\SueldoSemanas::confirmar((int) $f->id);
    echo "  {$f->fecha} {$f->name} semana {$f->semana_inicio}→{$f->semana_fin} \$" . number_format((int) $f->monto, 0, ',', '.') . " · $antes\n";
}
echo "Confirmados ahora con el aviso del banco: $n de " . count($filas) . "\n";
