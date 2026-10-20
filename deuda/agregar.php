<?php
// Agrega deudas a Por pagar (una sola vez cada una, por nombre).
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$sub = function ($cat, $sub) {
    $c = DB::table('categorias_compras')->where('nombre', $cat)->value('id');
    return [$c, $c ? DB::table('subcategorias_compras')->where('categoria_id', $c)->where('nombre', $sub)->value('id') : null];
};
$nuevas = [
    ['tipo' => 'cuenta', 'nombre' => 'CGE · cuenta 6624844 (luz)', 'acreedor' => 'CGE', 'patron' => 'cge, 6624844',
     'monto_total' => 203800, 'cat' => ['Energía y agua', 'Luz'], 'nota' => 'Sin pagar al 20-10-2026'],
    ['tipo' => 'credito', 'nombre' => 'Crédito Banco Falabella', 'acreedor' => 'Banco Falabella', 'patron' => 'falabella',
     'monto_total' => 10 * 1024827, 'cuota' => 1024827, 'cuotas_total' => 10, 'vencimiento' => '2026-11-15', 'ambito' => 'personal',
     'cat' => ['Financieros', 'Préstamos'], 'nota' => 'Quedan 10 cuotas al 20-10-2026; vence el 15 de cada mes'],
];
foreach ($nuevas as $n) {
    if (DB::table('fin_deudas')->where('nombre', $n['nombre'])->exists()) { echo "Ya estaba: {$n['nombre']}\n"; continue; }
    [$c, $s] = $sub($n['cat'][0], $n['cat'][1]);
    unset($n['cat']);
    $n['ambito'] = $n['ambito'] ?? 'botacura';
    DB::table('fin_deudas')->insert($n + ['ambito' => $n['ambito'] ?? 'botacura', 'estado' => 'pendiente', 'categoria_id' => $c, 'subcategoria_id' => $s,
        'created_at' => now(), 'updated_at' => now()]);
    echo "Agregada a Por pagar: {$n['nombre']} \$" . number_format($n['monto_total'], 0, ',', '.') . "\n";
}
$r = app(App\Finanzas\Egresos\Deudas::class)->resumen(['botacura', 'personal']);
echo 'Deuda pendiente total: $' . number_format($r['total'], 0, ',', '.') . "\n";
