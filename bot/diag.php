<?php
// Diagnóstico: envío de correos automáticos y remuneraciones marcadas desde el banco. Solo lee, no cambia nada.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function t($s) { return mb_substr(preg_replace('/\s+/', ' ', (string) $s), 0, 150); }

echo "=== CORREOS AUTOMÁTICOS ===\n";
echo 'Cola: ' . config('queue.default') . ' · Correo: ' . config('mail.driver', config('mail.default')) . ' ' . config('mail.host') . ':' . config('mail.port')
    . ' ' . config('mail.encryption') . ' · usuario ' . (config('mail.username') ? 'definido' : 'VACÍO') . ' · clave ' . (config('mail.password') ? 'definida' : 'VACÍA')
    . ' · desde ' . config('mail.from.address') . "\n";
$w = trim(shell_exec("ps -eo pid,etime,user,cmd | grep -E 'queue:(work|listen)|horizon' | grep -v grep") ?: '');
echo 'Procesos de la cola: ' . ($w ? "\n" . $w : 'NINGUNO (nadie está procesando la cola)') . "\n";
$sup = trim(shell_exec('(supervisorctl status 2>/dev/null || sudo -n supervisorctl status 2>/dev/null) | head -10') ?: '');
if ($sup) echo "Supervisor:\n$sup\n";
if (Schema::hasTable('jobs')) {
    foreach (DB::table('jobs')->selectRaw('queue, count(*) n, min(created_at) desde, max(attempts) intentos')->groupBy('queue')->get() as $r) {
        echo "En espera: cola {$r->queue}: {$r->n} trabajos desde " . date('d-m H:i', $r->desde) . " (máx intentos {$r->intentos})\n";
    }
    foreach (DB::table('jobs')->orderBy('id')->limit(8)->get() as $j) {
        $p = json_decode($j->payload, true);
        echo '  · ' . date('d-m H:i', $j->created_at) . ' ' . ($p['displayName'] ?? '?') . "\n";
    }
    if (!DB::table('jobs')->count()) echo "En espera: 0\n";
}
if (Schema::hasTable('failed_jobs')) {
    echo 'Fallidos (total ' . DB::table('failed_jobs')->count() . "), los últimos:\n";
    foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(10)->get() as $f) {
        $p = json_decode($f->payload, true);
        echo "  · {$f->failed_at} " . ($p['displayName'] ?? '?') . ' → ' . t(strtok($f->exception, "\n")) . "\n";
    }
}
$log = storage_path('logs/laravel.log');
if (is_file($log)) {
    $lin = preg_grep('/Swift|smtp|SMTP|Expected response|Connection could not|mail/i', explode("\n", shell_exec('tail -n 4000 ' . escapeshellarg($log))));
    echo "Últimos errores de correo en el log:\n";
    foreach (array_slice($lin, -6) as $l) echo '  · ' . t($l) . "\n";
}

echo "\n=== REMUNERACIONES DESDE EL BANCO (últimos 21 días) ===\n";
if (!Schema::hasTable('fin_correos')) { echo "No existe fin_correos\n"; exit; }
$cs = new App\Finanzas\Correo\ConciliadorSalidas();
$ss = app(App\Finanzas\SueldoSemanas::class);
$pagos = DB::table('fin_correos')->whereIn('tipo', ['pago', 'personal'])->where('fecha', '>=', date('Y-m-d', strtotime('-21 days')))
    ->orderBy('fecha')->get();
$n = 0;
foreach ($pagos as $c) {
    $como = null;
    $u = $cs->persona($c, $como);
    if (!$u) continue;
    $n++;
    echo "#{$c->id} {$c->fecha} {$c->tipo} \$" . number_format((int) $c->monto, 0, ',', '.') . " a {$c->nombre} (cta " . ($c->cuenta ?: '-') . ', rut ' . ($c->rut ?: '-') . ")\n";
    echo "   persona: {$u->name} (por $como) · estado {$c->estado}/" . ($c->vinculo ?: '-') . ' · seguridad ' . ($c->seguridad ?? '-') . ' · comentario "' . t($c->comentario) . "\"\n";
    echo '   nota: ' . t($c->nota) . "\n";
    echo '   ahora diría: ' . t(json_encode(array_intersect_key($cs->conciliar($c), array_flip(['estado', 'vinculo', 'nota'])), JSON_UNESCAPED_UNICODE)) . "\n";
    foreach ($ss->pendientes((int) $u->id, $c->fecha) as $s) {
        echo "     semana {$s['inicio']}→{$s['fin']} total \$" . number_format((int) $s['total'], 0, ',', '.') . ($s['pagado'] ? ' (pagada)' : ' (sin pagar)') . "\n";
    }
}
echo $n ? '' : "Ningún pago de los últimos 21 días calzó con un funcionario por RUT, cuenta o nombre.\n";
$sin = DB::table('fin_correos')->whereIn('tipo', ['pago', 'personal'])->where('fecha', '>=', date('Y-m-d', strtotime('-21 days')))->count() - $n;
echo "Otros pagos del periodo sin funcionario asociado: $sin\n";
echo 'Funcionarios con cuenta registrada: ' . DB::table('users')->whereNotNull('numero_cuenta_bancaria')->where('numero_cuenta_bancaria', '<>', '')->count()
    . ' · con RUT: ' . DB::table('users')->whereNotNull('rut')->where('rut', '<>', '')->count() . "\n";
