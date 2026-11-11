<?php
// Feriados para el bot (11/11/2026): sin mínimo de personas y sin programas S en feriados.
//   php x.php                -> agrega los feriados y muestra el estado del 8/12
//   php x.php habilitar      -> además habilita el martes 8/12 en el calendario
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$nuevos = ['2026-12-08', '2026-12-25', '2027-01-01'];
$actual = (string) DB::table('bot_secciones_texto')->where('clave', 'param_feriados')->value('contenido');
preg_match_all('/\d{4}-\d{2}-\d{2}/', $actual, $m);
$lista = array_values(array_unique(array_merge($m[0], $nuevos)));
sort($lista);
DB::table('bot_secciones_texto')->where('clave', 'param_feriados')->update(['contenido' => implode(',', $lista), 'updated_at' => now()]);
\Artisan::call('cache:clear');
echo "Feriados del bot: " . implode(', ', $lista) . "\n";
$f = '2026-12-08';
if (($argv[1] ?? '') === 'habilitar') {
    $fila = \App\FechaDisponible::where('fecha', $f)->first();
    if ($fila) { $fila->habilitada = true; $fila->save(); } else { \App\FechaDisponible::create(['fecha' => $f, 'tipo' => 'regular', 'habilitada' => true]); }
    echo "8/12 habilitado en el calendario.\n";
}
$hab = \App\FechaDisponible::where('fecha', $f)->where('habilitada', true)->exists();
echo "Martes 8/12 en el calendario: " . ($hab ? 'HABILITADO' : 'NO habilitado (el bot dirá que no hay disponibilidad; para abrirlo: ... | bash -s -- habilitar)') . "\n";
$pid = DB::table('programas')->where('nombre_programa', 'Wellness Plus')->value('id');
$c = \App\Services\BotTarifasService::cotizar(['programa_id' => $pid, 'fecha' => $f, 'personas' => 2, 'tipo_pago' => 'Transferencia']);
echo "Prueba: pareja en Wellness Plus el 8/12 → " . ($c['ok'] ? 'SE PUEDE RESERVAR' : 'no (' . implode(', ', array_column($c['errores'], 'codigo')) . ')') . "\n";
