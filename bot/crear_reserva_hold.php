<?php
// Crea la reserva de un cupo apartado (hold) que venció aunque el cliente sí pagó. (11/11/2026)
//   php x.php 24            -> muestra qué se crearía y si hay cupo
//   php x.php 24 crear once -> crea la reserva (y fija "once" si se indica desayuno|once)
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$id = (int) ($argv[1] ?? 0); $modo = $argv[2] ?? 'mostrar'; $comida = $argv[3] ?? null;
$hold = DB::table('reserva_holds')->where('id', $id)->first();
if (!$hold) { echo "No existe el hold #$id\n"; exit; }
if (!empty($hold->id_reserva)) { echo "El hold #$id ya tiene la reserva #{$hold->id_reserva}. No hago nada.\n"; exit; }
$prog = DB::table('programas')->where('id', $hold->programa_id)->first();
$cliente = DB::table('clientes')->whereIn('whatsapp_cliente', [$hold->telefono, substr($hold->telefono, -9)])->first();
if ($cliente) {
    $ya = DB::table('reservas')->where('cliente_id', $cliente->id)->whereRaw('DATE(fecha_visita) = ?', [$hold->fecha])->value('id');
    if ($ya) { echo "Ese cliente ya tiene la reserva #$ya para {$hold->fecha}. No hago nada.\n"; exit; }
}
echo "Hold #{$hold->id} ({$hold->estado}): {$hold->nombre} +{$hold->telefono} {$hold->email}\n";
echo "  {$hold->fecha} · {$hold->personas} personas · {$prog->nombre_programa} · {$hold->tipo_pago} · total \${$hold->valor_total} · abono \${$hold->abono_50} · saldo \${$hold->diferencia}\n";
$d = app(\App\Services\DisponibilidadService::class)->disponible($hold->fecha, $prog->espacio_tipo ?? null, (int) $hold->personas);
echo "  Cupo hoy: " . ($d['disponible'] ? 'SÍ' : 'NO (' . $d['razon'] . ')') . " · tinajas " . json_encode($d['tinaja']) . "\n";
$plan = \App\Services\BotUbicacionesService::plan($hold->fecha, (int) $hold->programa_id, (int) $hold->personas, null);
echo "  Ubicación: " . ($plan['texto_cliente'] ?? '-') . ' ' . json_encode($plan['asignacion']) . "\n";
if ($modo !== 'crear') { echo "Para crearla: ... | bash -s -- {$id} crear" . ($comida ? " $comida" : ' [desayuno|once]') . "\n"; exit; }
if (!$d['disponible']) { echo "Sin cupo: no la creo.\n"; exit; }
$r = app(\App\Http\Controllers\Api\BotReservaController::class)->convertirHoldEnReserva($hold);
if (empty($r['ok'])) { echo 'No se pudo: ' . ($r['motivo'] ?? json_encode($r)) . "\n"; exit; }
echo "RESERVA CREADA #{$r['reserva_id']} (venta #{$r['venta_id']}, abono \${$r['abono_50']}, saldo \${$r['diferencia']})\n";
if (in_array($comida, ['desayuno', 'once'], true)) {
    try { \App\Services\DesayunoOnceService::fijar((int) $r['reserva_id'], [$comida]); echo "  Comida fijada: $comida\n"; } catch (\Throwable $e) { echo "  No se pudo fijar la comida: " . $e->getMessage() . "\n"; }
}
try { \App\Services\BotCasoService::registrar('reserva_creada_manual', $hold->telefono, ['reserva_id' => $r['reserva_id'], 'hold_id' => $hold->id]); } catch (\Throwable $e) {}
echo "Revisa en el banco el abono de \${$hold->abono_50} y márcalo en la venta.\n";
