<?php

namespace App\Http\Controllers;

use App\TipoTransaccion;
use App\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Edición de la diferencia de una venta ya cerrada (solo administradores).
 * Cada cambio queda registrado en venta_diferencia_cambios.
 */
class VentaDiferenciaController extends Controller
{
    public function update(Request $request, Venta $venta)
    {
        if (!Auth::user() || !Auth::user()->is_admin()) {
            abort(403, 'Solo los administradores pueden editar la diferencia.');
        }

        if (is_null($venta->diferencia_programa)) {
            Alert::error('No se puede editar', 'La venta aún no está cerrada. Usa "Cerrar venta".');
            return back();
        }

        $request->merge([
            'diferencia' => (int) preg_replace('/[^0-9]/', '', (string) $request->diferencia),
        ]);

        $request->validate([
            'diferencia'                     => 'required|integer|min:0|max:100000000',
            'id_tipo_transaccion_diferencia' => 'nullable|exists:tipos_transacciones,id',
            'motivo'                         => 'nullable|string|max:255',
        ], [
            'diferencia.required' => 'Ingresa el monto de la diferencia.',
        ]);

        $anteriorMonto = (int) $venta->diferencia_programa;
        $anteriorTipo  = $venta->id_tipo_transaccion_diferencia;
        $nuevoMonto    = (int) $request->diferencia;
        $nuevoTipo     = $request->filled('id_tipo_transaccion_diferencia')
            ? (int) $request->id_tipo_transaccion_diferencia
            : $anteriorTipo;

        if ($anteriorMonto === $nuevoMonto && (int) $anteriorTipo === (int) $nuevoTipo) {
            Alert::info('Sin cambios', 'El monto y el medio de pago son los mismos.');
            return back();
        }

        DB::transaction(function () use ($venta, $request, $anteriorMonto, $anteriorTipo, $nuevoMonto, $nuevoTipo) {
            DB::table('venta_diferencia_cambios')->insert([
                'id_venta'          => $venta->id,
                'id_reserva'        => $venta->id_reserva,
                'id_user'           => Auth::id(),
                'monto_anterior'    => $anteriorMonto,
                'monto_nuevo'       => $nuevoMonto,
                'id_tipo_anterior'  => $anteriorTipo,
                'id_tipo_nuevo'     => $nuevoTipo,
                'motivo'            => $request->motivo,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $venta->diferencia_programa = $nuevoMonto;
            $venta->id_tipo_transaccion_diferencia = $nuevoTipo;
            $venta->save();

            // El pago del cierre guarda el mismo medio de pago en id_tipo_transaccion1.
            if ((int) $anteriorTipo !== (int) $nuevoTipo && $venta->pagoConsumo) {
                $pago = $venta->pagoConsumo;
                if ((int) $pago->id_tipo_transaccion1 === (int) $anteriorTipo || is_null($pago->id_tipo_transaccion1)) {
                    $pago->id_tipo_transaccion1 = $nuevoTipo;
                    $pago->save();
                }
            }
        });

        Log::info('Diferencia editada', [
            'venta' => $venta->id, 'usuario' => Auth::id(),
            'de' => $anteriorMonto, 'a' => $nuevoMonto,
            'tipo_de' => $anteriorTipo, 'tipo_a' => $nuevoTipo,
        ]);

        $medio = optional(TipoTransaccion::find($nuevoTipo))->nombre;
        Alert::success('Diferencia actualizada',
            'De $' . number_format($anteriorMonto, 0, '', '.') . ' a $' . number_format($nuevoMonto, 0, '', '.')
            . ($medio ? ' (' . $medio . ')' : ''));

        return back();
    }
}
