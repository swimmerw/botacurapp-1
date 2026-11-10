<?php
// Crea la tabla y carga los comprobantes de los ultimos 30 dias que no calzan con ninguna reserva del mismo telefono.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\ComprobanteTextoService as CT;

if (!Schema::hasTable('comprobantes_sin_reserva')) {
    Schema::create('comprobantes_sin_reserva', function ($t) {
        $t->increments('id');
        $t->string('telefono', 30)->index();
        $t->string('nombre', 150)->nullable();
        $t->integer('monto')->nullable();
        $t->date('fecha_pago')->nullable();
        $t->string('glosa', 120)->nullable();
        $t->text('descripcion')->nullable();
        $t->string('archivo', 255)->nullable();
        $t->string('phone_number_id', 40)->nullable();
        $t->string('estado', 20)->default('pendiente')->index();
        $t->unsignedInteger('reserva_id')->nullable();
        $t->unsignedInteger('venta_id')->nullable();
        $t->string('nota', 300)->nullable();
        $t->unsignedBigInteger('revisado_por')->nullable();
        $t->timestamp('revisado_at')->nullable();
        $t->timestamps();
    });
    echo "Tabla creada\n";
}
$n = 0; $ok = 0;
$convs = DB::table('bot_conversaciones')->where('canal', 'whatsapp')->where('updated_at', '>=', now()->subDays(30)->toDateTimeString())->get();
foreach ($convs as $c) {
    foreach (json_decode($c->historial_json ?: '[]', true) ?: [] as $t) {
        if (($t['role'] ?? '') !== 'user') { continue; }
        foreach (preg_split('/(?=\[Imagen recibida\])/u', (string) $t['content']) as $b) {
            if (strpos($b, '[Imagen recibida]') !== 0 || !preg_match('/comprobante|transferencia|transacci[oó]n|dep[oó]sito/iu', $b)) { continue; }
            $b = mb_substr(preg_replace('/\[Sistema[^\]]*\]/u', '', str_replace('[Imagen recibida]:', '', $b)), 0, 1800);
            $monto = CT::monto($b);
            $fila = (object) ['telefono' => $c->usuario_id, 'monto' => $monto];
            $calza = false;
            foreach (CT::candidatas($fila) as $r) {
                if ($r->motivo !== 'mismo teléfono' || !$monto) { continue; }
                $tot = (int) $r->abono_programa + (int) $r->diferencia_programa;
                if (abs((int) $r->abono_programa - $monto) <= max(1000, $monto * 0.03) || abs($tot - $monto) <= max(1000, $monto * 0.03)) { $calza = true; }
            }
            if ($calza) { $ok++; continue; }
            $id = CT::registrar($c->usuario_id, $c->nombre_cliente, $b, null, null, $c->updated_at);
            $n++;
            // Mickael: ya se registró a mano en la reserva 2803 (09/11)
            if (CT::n9($c->usuario_id) === '942951595' && $monto === 110000) {
                DB::table('comprobantes_sin_reserva')->where('id', $id)->update(['estado' => 'asociado', 'reserva_id' => 2803, 'venta_id' => 2802,
                    'nota' => 'Registrado a mano el 09/11', 'revisado_at' => now()]);
            }
        }
    }
}
echo "Comprobantes que ya calzaban con su reserva: {$ok}\n";
echo "Comprobantes sin reserva cargados: " . DB::table('comprobantes_sin_reserva')->where('estado', 'pendiente')->count() . " pendientes\n";
