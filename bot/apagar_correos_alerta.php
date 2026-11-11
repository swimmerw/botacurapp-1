<?php
// Apaga los correos de alerta del bot (11/11/2026). Todas las alertas (derivaciones, comprobantes, Instagram,
// días pedidos) leen el mismo destinatario "alerta_correo": se deja vacío y siguen quedando en la app.
//   sin argumento: apaga     restaurar: vuelve a poner el correo guardado
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$resp = storage_path('app/alerta_correo_respaldo.txt');
$actual = (string) DB::table('bot_secciones_texto')->where('clave', 'alerta_correo')->value('contenido');
if (($argv[1] ?? '') === 'restaurar') {
    $v = is_file($resp) ? trim(file_get_contents($resp)) : '';
    if ($v === '') { echo "No hay respaldo para restaurar.\n"; exit; }
    DB::table('bot_secciones_texto')->where('clave', 'alerta_correo')->update(['contenido' => $v, 'updated_at' => now()]);
    echo "Correos de alerta reactivados.\n"; exit;
}
if (trim($actual) === '') {
    echo "Los correos de alerta ya estaban apagados.\n";
} else {
    file_put_contents($resp, $actual);
    DB::table('bot_secciones_texto')->where('clave', 'alerta_correo')->update(['contenido' => '', 'updated_at' => now()]);
    $masc = preg_replace('/(?<=.).(?=[^@]*@)/', '*', $actual);
    echo "Correos de alerta APAGADOS (antes iban a {$masc}; respaldo en storage/app/alerta_correo_respaldo.txt).\n";
}
\Artisan::call('cache:clear');
echo "\nOtros envíos de correo en el código (revisión, no se tocan):\n";
foreach (explode("\n", trim(shell_exec("grep -rln 'Mail::' " . base_path('app') . " 2>/dev/null"))) as $f) {
    if ($f === '') { continue; }
    $usaAlerta = strpos(file_get_contents($f), 'alerta_correo') !== false;
    echo '  ' . str_replace(base_path() . '/', '', $f) . ($usaAlerta ? '  → alerta del bot: APAGADA' : '  → otro envío (clientes, gift card, sueldos, etc.)') . "\n";
}
echo "\nAlertas que siguen en la app (pendientes sin atender): " . DB::table('bot_mensajes_pendientes')->where('estado', 'pendiente')->count() . "\n";
