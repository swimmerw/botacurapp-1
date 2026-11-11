<?php
// Marca como atendidas TODAS las alertas pendientes de la app (bot_mensajes_pendientes). 11/11/2026
// Guarda los id para poder deshacer: php x.php deshacer
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$M = \App\BotMensajePendiente::class;
$f = storage_path('app/alertas_limpiadas_20261111.json');
if (($argv[1] ?? '') === 'deshacer') {
    $ids = is_file($f) ? json_decode(file_get_contents($f), true) : [];
    $n = $ids ? $M::whereIn('id', $ids)->where('estado', 'atendido')->update(['estado' => 'pendiente']) : 0;
    echo "Vueltas a pendiente: $n\n"; exit;
}
$q = $M::where('estado', 'pendiente');
echo "Alertas pendientes por tipo:\n";
foreach ((clone $q)->select('tipo', DB::raw('COUNT(*) n'), DB::raw('MIN(created_at) desde'))->groupBy('tipo')->get() as $r) {
    echo "  " . str_pad($r->tipo ?: '(sin tipo)', 14) . " {$r->n}  (desde {$r->desde})\n";
}
$ids = (clone $q)->pluck('id')->all();
$prev = is_file($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
file_put_contents($f, json_encode(array_values(array_unique(array_merge($prev, $ids)))));
$n = $ids ? $M::whereIn('id', $ids)->update(['estado' => 'atendido', 'updated_at' => now()]) : 0;
echo "\nMarcadas como atendidas: $n. Quedan pendientes: " . $M::where('estado', 'pendiente')->count() . "\n";
try {
    echo "No se tocaron (revisar aparte): comprobantes en revisión pendientes = " . DB::table('comprobantes_revision')->where('estado', 'pendiente')->count() . "\n";
} catch (\Throwable $e) {}
