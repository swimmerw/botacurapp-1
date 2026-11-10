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
        }
        $resueltos = DB::table('comprobantes_sin_reserva')->where('estado', '!=', 'pendiente')
            ->orderBy('revisado_at', 'desc')->limit(15)->get();
        return view('themes.backoffice.pages.comprobantes-sin-reserva.index', compact('pendientes', 'resueltos'));
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
