<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reservas creadas en un dia y suma de los abonos recibidos (13/10/2026).
 * Abono del dia = abono de las reservas creadas ese dia (sin las pagadas con Gift Card)
 * + abonos extra registrados ese dia. Las Gift Cards se muestran aparte (no traen medio de pago).
 */
class ReservasDelDiaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ($user->has_role(config('app.admin_role')) || $user->has_role(config('app.jefe_local_role'))), 403);

        try {
            $fecha = Carbon::parse($request->input('fecha', now()->toDateString()))->startOfDay();
        } catch (\Exception $e) {
            $fecha = now()->startOfDay();
        }
        $dia = $fecha->toDateString();

        $tieneFuente = Schema::hasColumn('reservas', 'fuente');
        $tieneEstadoPago = Schema::hasColumn('ventas', 'estado_pago');
        $tieneAlerta = Schema::hasColumn('ventas', 'comprobante_alerta');

        $reservas = DB::table('reservas')
            ->leftJoin('clientes', 'clientes.id', '=', 'reservas.cliente_id')
            ->leftJoin('programas', 'programas.id', '=', 'reservas.id_programa')
            ->leftJoin('ventas', 'ventas.id_reserva', '=', 'reservas.id')
            ->leftJoin('tipos_transacciones as tt', 'tt.id', '=', 'ventas.id_tipo_transaccion_abono')
            ->leftJoin('users', 'users.id', '=', 'reservas.user_id')
            ->whereDate('reservas.created_at', $dia)
            ->orderBy('reservas.created_at')
            ->select(
                'reservas.id', 'reservas.created_at', 'reservas.fecha_visita', 'reservas.cantidad_personas',
                'clientes.nombre_cliente', 'programas.nombre_programa',
                'ventas.id as venta_id', 'ventas.abono_programa', 'ventas.diferencia_programa', 'ventas.total_pagar',
                'tt.nombre as medio_abono', 'users.name as usuario',
                DB::raw($tieneFuente ? 'reservas.fuente' : "NULL as fuente"),
                DB::raw($tieneEstadoPago ? 'ventas.estado_pago' : "NULL as estado_pago"),
                DB::raw($tieneAlerta ? 'ventas.comprobante_alerta' : "NULL as comprobante_alerta"),
                DB::raw('EXISTS(SELECT 1 FROM gift_cards gc WHERE gc.id_venta = ventas.id) as con_giftcard')
            )
            ->get();

        $abonosExtra = Schema::hasTable('abonos_extra') ? DB::table('abonos_extra')
            ->leftJoin('ventas', 'ventas.id', '=', 'abonos_extra.id_venta')
            ->leftJoin('reservas', 'reservas.id', '=', 'ventas.id_reserva')
            ->leftJoin('clientes', 'clientes.id', '=', 'reservas.cliente_id')
            ->leftJoin('tipos_transacciones as tt', 'tt.id', '=', 'abonos_extra.id_tipo_transaccion')
            ->whereDate('abonos_extra.fecha_abono', $dia)
            ->select('abonos_extra.monto', 'reservas.id as reserva_id', 'reservas.fecha_visita', 'clientes.nombre_cliente', 'tt.nombre as medio')
            ->get() : collect();

        // Totales
        $porMedio = [];
        $totalAbonos = 0;
        $totalGiftCard = 0;
        $totalValor = 0;
        $porCobrar = 0;
        $personas = 0;
        foreach ($reservas as $r) {
            $abono = (int) $r->abono_programa;
            $personas += (int) $r->cantidad_personas;
            $totalValor += $abono + (int) $r->diferencia_programa;
            $porCobrar += (int) ($r->total_pagar ?? $r->diferencia_programa);
            if ($r->con_giftcard) {
                $totalGiftCard += $abono;
                continue;
            }
            $totalAbonos += $abono;
            $medio = $r->medio_abono ?: 'Sin medio de pago';
            $porMedio[$medio] = ($porMedio[$medio] ?? 0) + $abono;
        }
        $totalExtra = 0;
        foreach ($abonosExtra as $a) {
            $totalExtra += (int) $a->monto;
            $medio = $a->medio ?: 'Sin medio de pago';
            $porMedio[$medio] = ($porMedio[$medio] ?? 0) + (int) $a->monto;
        }
        arsort($porMedio);

        $resumen = [
            'reservas' => $reservas->count(),
            'personas' => $personas,
            'abonos' => $totalAbonos,
            'extra' => $totalExtra,
            'recibido' => $totalAbonos + $totalExtra,
            'giftcard' => $totalGiftCard,
            'valor' => $totalValor,
            'por_cobrar' => $porCobrar,
        ];

        // Comprobantes del bot que esperan el pase de una persona (todos los pendientes, de cualquier dia)
        $porRevisar = Schema::hasTable('comprobantes_revision') ? DB::table('comprobantes_revision as cr')
            ->leftJoin('reserva_holds as h', 'h.id', '=', 'cr.hold_id')
            ->leftJoin('programas', 'programas.id', '=', 'h.programa_id')
            ->where('cr.estado', 'pendiente')
            ->orderBy('cr.created_at')
            ->select('cr.*', 'h.nombre', 'h.fecha as fecha_visita', 'h.personas', 'h.abono_50', 'h.valor_total',
                'h.estado as estado_hold', 'h.expira_en', 'programas.nombre_programa')
            ->get()
            ->map(function ($c) {
                $c->datos = json_decode($c->datos_json ?? '{}', true) ?: [];
                return $c;
            }) : collect();

        return view('themes.backoffice.pages.reporte.reservas_dia', compact('fecha', 'reservas', 'abonosExtra', 'porMedio', 'resumen', 'porRevisar'));
    }

    private function autorizar()
    {
        $user = auth()->user();
        abort_unless($user && ($user->has_role(config('app.admin_role')) || $user->has_role(config('app.jefe_local_role'))), 403);
    }

    /** Imagen del comprobante en revision. */
    public function comprobante($id)
    {
        $this->autorizar();
        $c = DB::table('comprobantes_revision')->where('id', $id)->first();
        abort_if(!$c || !$c->archivo || !\Storage::disk('comprobante_transferencia')->exists($c->archivo), 404);
        return \Storage::disk('comprobante_transferencia')->response($c->archivo);
    }

    /** Una persona da el pase: se crea la reserva, se marca pagada y se avisa al cliente. */
    public function aprobar($id)
    {
        $this->autorizar();
        $c = DB::table('comprobantes_revision')->where('id', $id)->where('estado', 'pendiente')->first();
        if (!$c) {
            return back()->with('error', 'Ese comprobante ya fue revisado.');
        }
        $hold = DB::table('reserva_holds')->where('id', $c->hold_id)->first();
        if (!$hold) {
            return back()->with('error', 'No se encontro el cupo apartado de este comprobante.');
        }
        $conv = app(\App\Http\Controllers\Api\BotReservaController::class)->convertirHoldEnReserva($hold);
        if (!($conv['ok'] ?? false)) {
            return back()->with('error', 'No se pudo crear la reserva: ' . ($conv['motivo'] ?? 'sin cupo') . '. Avisale al cliente por WhatsApp.');
        }
        $d = json_decode($c->datos_json ?? '{}', true) ?: [];
        $monto = (isset($d['monto']) && is_numeric($d['monto'])) ? (int) $d['monto'] : null;
        $total = (int) $conv['abono_50'] + (int) $conv['diferencia'];
        $abono = $monto ?? (int) $conv['abono_50'];
        $cambios = [
            'abono_programa' => $abono,
            'diferencia_programa' => max(0, $total - $abono),
            'total_pagar' => max(0, $total - $abono),
            'estado_pago' => 'pendiente_verificacion',
            'updated_at' => now(),
        ];
        $extra = [
            'comprobante_transferencia' => $c->archivo,
            'comprobante_monto' => $monto,
            'comprobante_fecha' => (!empty($d['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['fecha'])) ? $d['fecha'] : null,
            'comprobante_hora' => !empty($d['hora']) ? substr($d['hora'], 0, 10) : null,
            'comprobante_numero_operacion' => !empty($d['numero_operacion']) ? substr($d['numero_operacion'], 0, 100) : null,
            'comprobante_nombre_origen' => !empty($d['nombre_origen']) ? substr($d['nombre_origen'], 0, 200) : null,
            'comprobante_alerta' => 'Aprobado por ' . (auth()->user()->name ?? 'el equipo') . ' pese a: ' . $c->motivos,
        ];
        foreach ($extra as $col => $val) {
            if (Schema::hasColumn('ventas', $col)) {
                $cambios[$col] = $val;
            }
        }
        if (Schema::hasColumn('ventas', 'fecha_abono')) {
            $cambios['fecha_abono'] = $cambios['comprobante_fecha'] ?? now()->toDateString();
        }
        DB::table('ventas')->where('id', $conv['venta_id'])->update($cambios);
        DB::table('comprobantes_revision')->where('id', $id)->update([
            'estado' => 'aprobado', 'reserva_id' => $conv['reserva_id'], 'revisado_por' => auth()->id(),
            'revisado_at' => now(), 'updated_at' => now(),
        ]);
        try {
            \App\Services\BotCasoService::registrar('comprobante_aprobado', $c->telefono, ['reserva' => $conv['reserva_id'], 'por' => auth()->user()->name ?? '']);
        } catch (\Throwable $e) {
        }
        // Mismo paso que Verificacion de pagos: marca pagada, confirma la reserva y avisa al cliente (con el menu)
        app(\App\Http\Controllers\VerificacionPagoController::class)->aprobar($conv['venta_id']);

        return back()->with('success', 'Reserva N°' . $conv['reserva_id'] . ' creada y confirmada. Se le aviso al cliente.');
    }

    /** Una persona rechaza: no se crea la reserva y se le pide al cliente el comprobante correcto. */
    public function rechazar(Request $request, $id)
    {
        $this->autorizar();
        $c = DB::table('comprobantes_revision')->where('id', $id)->where('estado', 'pendiente')->first();
        if (!$c) {
            return back()->with('error', 'Ese comprobante ya fue revisado.');
        }
        $nota = trim((string) $request->input('motivo', ''));
        $hasta = now()->addHour();
        DB::table('reserva_holds')->where('id', $c->hold_id)->where('estado', 'activo')->update(['expira_en' => $hasta, 'updated_at' => now()]);
        DB::table('comprobantes_revision')->where('id', $id)->update([
            'estado' => 'rechazado', 'nota' => $nota ?: null, 'revisado_por' => auth()->id(), 'revisado_at' => now(), 'updated_at' => now(),
        ]);
        $hold = DB::table('reserva_holds')->where('id', $c->hold_id)->first();
        $nombre = $hold ? strtok((string) $hold->nombre, ' ') : '';
        $texto = 'Hola' . ($nombre ? ' ' . $nombre : '') . ', revisamos tu comprobante y no pudimos validarlo'
            . ($nota ? ': ' . $nota : '') . '. Por favor envíanos por este chat el comprobante correcto de la transferencia a Botacura. '
            . 'Tu cupo queda apartado hasta las ' . $hasta->copy()->timezone('America/Santiago')->format('H:i') . ' hrs.';
        $enviado = $this->enviarWhatsApp($c->telefono, $texto, $c->phone_number_id);
        try {
            \App\Services\BotCasoService::registrar('comprobante_rechazado_equipo', $c->telefono, ['nota' => $nota, 'por' => auth()->user()->name ?? '']);
        } catch (\Throwable $e) {
        }

        return back()->with($enviado ? 'success' : 'error', $enviado
            ? 'Comprobante rechazado. Se le pidio al cliente el comprobante correcto.'
            : 'Comprobante rechazado, pero no se pudo enviar el WhatsApp al cliente: escribele tu.');
    }

    private function enviarWhatsApp(string $telefono, string $texto, $phoneNumberId = null): bool
    {
        try {
            $phoneId = $phoneNumberId ?: env('META_PHONE_NUMBER_ID');
            $client = new \GuzzleHttp\Client(['timeout' => 10, 'http_errors' => false]);
            $res = $client->post('https://graph.facebook.com/' . env('META_API_VERSION', 'v19.0') . '/' . $phoneId . '/messages', [
                'headers' => ['Authorization' => 'Bearer ' . env('META_WHATSAPP_TOKEN'), 'Content-Type' => 'application/json'],
                'json' => ['messaging_product' => 'whatsapp', 'to' => $telefono, 'type' => 'text', 'text' => ['body' => $texto]],
            ]);
            return $res->getStatusCode() < 300;
        } catch (\Throwable $e) {
            \Log::error('[ReservasDelDia] WhatsApp no enviado: ' . $e->getMessage());
            return false;
        }
    }
}
