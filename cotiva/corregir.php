<?php
// Lista las cotizaciones marcadas "con IVA incluido" y, si se pide, las pasa a valores netos (+19%).
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$arg = isset($argv[1]) ? $argv[1] : '';
$rows = App\Cotizacion::with('items')->where('iva_incluido', 1)->orderBy('id')->get();
echo "Cotizaciones con 'IVA incluido': " . $rows->count() . "\n";
foreach ($rows as $c) {
    $s = (int) $c->items->sum('total');
    printf("  N.%-5d %s  %-28s suma items \$%s -> hoy dice total \$%s; como neto seria total \$%s\n", $c->id, substr($c->created_at, 0, 10),
        mb_substr($c->cliente, 0, 28), number_format($s, 0, ',', '.'), number_format($s, 0, ',', '.'), number_format(round($s * 1.19), 0, ',', '.'));
}
if ($arg === '') { echo "\nNo cambie nada. Para corregir: todas, o los numeros separados por coma.\n"; exit; }
$q = App\Cotizacion::where('iva_incluido', 1);
if ($arg !== 'todas') $q->whereIn('id', array_map('intval', explode(',', $arg)));
echo "\nCorregidas (ahora neto + IVA): " . $q->update(['iva_incluido' => 0]) . "\n";
