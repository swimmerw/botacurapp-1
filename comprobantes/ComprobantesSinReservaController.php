<?php

namespace App\Http\Controllers;

use App\Services\ComprobanteTextoService as CT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Comprobantes sin reserva (10/11/2026): comprobantes que llegaron por WhatsApp y que el bot no pudo
 * asociar a ningún cupo apartado ni reserva. El equipo los asocia a una reserva (y pasan a
 * Verificación de pagos) o los descarta.
 */
class ComprobantesSinReservaController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->check(), 403);
        $pendientes = DB::table('comprobantes_sin_reserva')->where('estado', 'pendiente')->orderBy('created_at')->get();
        foreach ($pendientes as $c) {
            if (!$c->nombre) {
                $c->nombre = DB::table('bot_conversaciones')->where('usuario_id', $c->telefono)->whereNotNull('nombre_cliente')->orderBy('id', 'desc')->value('nombre_cliente');
            }
            $c->candidatas = CT::candidatas($c);
            $c->chat = DB::table('bot_conversaciones')->where('usuario_id', $c->telefono)->orderBy('id', 'desc')
                ->first(['nombre_cliente', 'correo', 'fecha_visita', 'cantidad_personas', 'id_programa']);
        }
        $programas = DB::table('programas')->orderBy('nombre_programa')->get(['id', 'nombre_programa', 'valor_programa']);
        $resueltos = DB::table('comprobantes_sin_reserva')->where('estado', '!=', 'pendiente')
            ->orderBy('revisado_at', 'desc')->limit(15)->get();
        return view('themes.backoffice.pages.comprobantes-sin-reserva.index', compact('pendientes', 'resueltos', 'programas'));
    }

    public function imagen($id)
    {
        $c = DB::table('comprobantes_sin_reserva')->where('id', $id)->first();
        abort_if(!$c || !$c->archivo || !Storage::disk('comprobante_transferencia')->exists($c->archivo), 404);
        return Storage::disk('comprobante_transferencia')->response($c->archivo);
    }

    public function asociar(Request $request, $id)
    {
        $request->validate(['reserva_id' => 'required|integer']);
        $c = DB::table('comprobantes_sin_reserva')->where('id', $id)->where('estado', 'pendiente')->first();
        abort_if(!$c, 404);
        $venta = DB::table('ventas')->where('id_reserva', (int) $request->reserva_id)->orderBy('id', 'desc')->first();
        if (!$venta) {
            return back()->with('error', 'La reserva N°' . (int) $request->reserva_id . ' no existe o no tiene venta.');
        }
        $nota = 'Comprobante asociado a mano (WhatsApp +' . $c->telefono . ($c->monto ? ', $' . number_format($c->monto, 0, ',', '.') : '')
            . ($c->fecha_pago ? ', ' . date('d/m/Y', strtotime($c->fecha_pago)) : '') . ($c->glosa ? ', «' . $c->glosa . '»' : '') . ')';
        $upd = [
            'comprobante_alerta' => trim(($venta->comprobante_alerta ? $venta->comprobante_alerta . ' | ' : '') . $nota),
            'estado_pago' => in_array($venta->estado_pago, ['pagado'], true) ? $venta->estado_pago : 'pendiente_verificacion',
            'updated_at' => now(),
        ];
        if (empty($venta->comprobante_transferencia)) {
            $upd['comprobante_transferencia'] = $c->archivo;
            $upd['comprobante_monto'] = $c->monto;
            $upd['comprobante_fecha'] = $c->fecha_pago;
        }
        DB::table('ventas')->where('id', $venta->id)->update($upd);
        DB::table('comprobantes_sin_reserva')->where('id', $id)->update([
            'estado' => 'asociado', 'reserva_id' => (int) $request->reserva_id, 'venta_id' => $venta->id,
            'revisado_por' => auth()->id(), 'revisado_at' => now(), 'updated_at' => now(),
        ]);
        \App\BotMensajePendiente::where('telefono', 'like', '%' . CT::n9($c->telefono))->where('estado', 'pendiente')->where('tipo', 'comprobante')
            ->update(['estado' => 'atendido', 'updated_at' => now()]);
        Log::info('[ComprobantesSinReserva] Asociado', ['id' => $id, 'reserva' => $request->reserva_id, 'por' => auth()->id()]);
        $msg = 'Comprobante asociado a la reserva N°' . (int) $request->reserva_id . '.';
        $msg .= $upd['estado_pago'] === 'pendiente_verificacion' ? ' Quedó en Verificación de pagos para aprobarlo.' : ' La venta ya estaba pagada: solo se agregó la nota.';
        return redirect()->route('backoffice.comprobantes-sin-reserva.index')->with('success', $msg);
    }

    /** Crea la reserva desde el comprobante (mismo camino que el bot: revalida cupo, crea cliente, reserva y venta). */
    public function crear(Request $request, $id)
    {
        $d = $request->validate([
            'nombre' => 'required|string|max:200', 'telefono' => 'required|string|max:20', 'email' => 'nullable|email|max:200',
            'programa_id' => 'required|integer|exists:programas,id', 'fecha' => 'required|date', 'personas' => 'required|integer|min:1|max:60',
        ]);
        $c = DB::table('comprobantes_sin_reserva')->where('id', $id)->where('estado', 'pendiente')->first();
        abort_if(!$c, 404);
        $tel = preg_replace('/\D/', '', $d['telefono']);
        if (strlen($tel) === 9) { $tel = '56' . $tel; }
        $holdId = DB::table('reserva_holds')->insertGetId([
            'telefono' => $tel, 'nombre' => $d['nombre'], 'email' => $d['email'] ?: '', 'programa_id' => (int) $d['programa_id'],
            'fecha' => $d['fecha'], 'personas' => (int) $d['personas'], 'masajes_extra' => 0, 'desayuno_once' => 0, 'tipo_pago' => 'Transferencia',
            'valor_total' => 0, 'abono_50' => 0, 'diferencia' => 0, 'estado' => 'activo', 'expira_en' => now()->addMinutes(10),
            'datos_json' => json_encode(['origen' => 'comprobantes_sin_reserva', 'comprobante_id' => (int) $id, 'telefono_chat' => $c->telefono]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $res = app(\App\Http\Controllers\Api\BotReservaController::class)->convertirHoldEnReserva(DB::table('reserva_holds')->find($holdId));
        if (empty($res['ok'])) {
            DB::table('reserva_holds')->where('id', $holdId)->update(['estado' => 'cancelado', 'updated_at' => now()]);
            return back()->with('error', 'No se pudo crear la reserva: ' . ($res['motivo'] ?? 'sin cupo para esa fecha y programa') . '.');
        }
        $rid = (int) $res['reserva_id'];
        $venta = DB::table('ventas')->where('id_reserva', $rid)->first();
        $total = (int) $venta->abono_programa + (int) $venta->diferencia_programa;
        $pagado = $c->monto ? min((int) $c->monto, $total ?: (int) $c->monto) : (int) $venta->abono_programa;
        DB::table('ventas')->where('id', $venta->id)->update([
            'comprobante_transferencia' => $c->archivo, 'comprobante_monto' => $c->monto, 'comprobante_fecha' => $c->fecha_pago,
            'comprobante_alerta' => 'Reserva creada desde Comprobantes sin reserva (WhatsApp +' . $c->telefono . ($c->glosa ? ', «' . $c->glosa . '»' : '') . ')'
                . ($c->monto && $total && (int) $c->monto !== (int) $venta->abono_programa && (int) $c->monto !== $total ? ' | el monto del comprobante no calza con el abono ni el total: revisar' : ''),
            'abono_programa' => $pagado, 'diferencia_programa' => max(0, $total - $pagado), 'total_pagar' => max(0, $total - $pagado),
            'estado_pago' => 'pendiente_verificacion', 'updated_at' => now(),
        ]);
        DB::table('reserva_holds')->where('id', $holdId)->update(['estado' => 'confirmado', 'id_reserva' => $rid, 'updated_at' => now()]);
        DB::table('comprobantes_sin_reserva')->where('id', $id)->update(['estado' => 'asociado', 'reserva_id' => $rid, 'venta_id' => $venta->id,
            'nota' => 'Reserva creada desde esta pantalla', 'revisado_por' => auth()->id(), 'revisado_at' => now(), 'updated_at' => now()]);
        Log::info('[ComprobantesSinReserva] Reserva creada', ['id' => $id, 'reserva' => $rid, 'por' => auth()->id()]);
        return redirect()->route('backoffice.comprobantes-sin-reserva.index')
            ->with('success', 'Reserva N°' . $rid . ' creada con el comprobante. Quedó en Verificación de pagos para aprobarla (al aprobar se le avisa al cliente).');
    }

    public function descartar(Request $request, $id)
    {
        $c = DB::table('comprobantes_sin_reserva')->where('id', $id)->where('estado', 'pendiente')->first();
        abort_if(!$c, 404);
        DB::table('comprobantes_sin_reserva')->where('id', $id)->update([
            'estado' => 'descartado', 'nota' => mb_substr((string) $request->input('nota', ''), 0, 300),
            'revisado_por' => auth()->id(), 'revisado_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('backoffice.comprobantes-sin-reserva.index')->with('success', 'Comprobante descartado.');
    }
}
