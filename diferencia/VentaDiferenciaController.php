<?php

namespace App\Http\Controllers;

use App\TipoTransaccion;
use App\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Edición de la diferencia de una venta (solo administradores): antes del cierre corrige
 * la diferencia por pagar (total_pagar); después, la diferencia cobrada y su medio de pago.
 * Cada cambio queda registrado en venta_diferencia_cambios.
 */
class VentaDiferenciaController extends Controller
{
    public function update(Request $request, Venta $venta)
    {
        if (!Auth::user() || !Auth::user()->is_admin()) {
            abort(403, 'Solo los administradores pueden editar la diferencia.');
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

        $cerrada = !is_null($venta->diferencia_programa);

        if (!$cerrada && $venta->tiene_saldo_a_favor) {
            Alert::error('No se puede editar', 'Esta venta tiene saldo a favor; corrígelo desde la reserva.');
            return back();
        }

        // Venta abierta: se corrige la diferencia por pagar (total_pagar), que es la que
        // usa el cierre y la boleta. Venta cerrada: se corrige diferencia_programa.
        $campo         = $cerrada ? 'diferencia_programa' : 'total_pagar';
        $anteriorMonto = (int) $venta->{$campo};
        $anteriorTipo  = $venta->id_tipo_transaccion_diferencia;
        $nuevoMonto    = (int) $request->diferencia;
        $nuevoTipo     = ($cerrada && $request->filled('id_tipo_transaccion_diferencia'))
            ? (int) $request->id_tipo_transaccion_diferencia
            : $anteriorTipo;

        if ($anteriorMonto === $nuevoMonto && (int) $anteriorTipo === (int) $nuevoTipo) {
            Alert::info('Sin cambios', 'El monto y el medio de pago son los mismos.');
            return $this->volver($request, $venta);
        }

        DB::transaction(function () use ($venta, $request, $campo, $cerrada, $anteriorMonto, $anteriorTipo, $nuevoMonto, $nuevoTipo) {
            $fila = [
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
            ];
            if (Schema::hasColumn('venta_diferencia_cambios', 'campo')) {
                $fila['campo'] = $campo;
            }
            DB::table('venta_diferencia_cambios')->insert($fila);

            $venta->{$campo} = $nuevoMonto;
            if ($cerrada) {
                $venta->id_tipo_transaccion_diferencia = $nuevoTipo;
            }
            $venta->save();

            // El pago del cierre guarda el mismo medio de pago en id_tipo_transaccion1.
            if ($cerrada && (int) $anteriorTipo !== (int) $nuevoTipo && $venta->pagoConsumo) {
                $pago = $venta->pagoConsumo;
                if ((int) $pago->id_tipo_transaccion1 === (int) $anteriorTipo || is_null($pago->id_tipo_transaccion1)) {
                    $pago->id_tipo_transaccion1 = $nuevoTipo;
                    $pago->save();
                }
            }
        });

        Log::info('Diferencia editada', [
            'venta' => $venta->id, 'usuario' => Auth::id(), 'campo' => $campo,
            'de' => $anteriorMonto, 'a' => $nuevoMonto,
            'tipo_de' => $anteriorTipo, 'tipo_a' => $nuevoTipo,
        ]);

        $medio = $cerrada ? optional(TipoTransaccion::find($nuevoTipo))->nombre : null;
        Alert::success('Diferencia actualizada',
            'De $' . number_format($anteriorMonto, 0, '', '.') . ' a $' . number_format($nuevoMonto, 0, '', '.')
            . ($medio ? ' (' . $medio . ')' : '')
            . ($cerrada ? '' : '. Ya puedes cerrar la venta con el monto correcto.'));

        return $this->volver($request, $venta);
    }

    private function volver(Request $request, Venta $venta)
    {
        if ($request->input('ir_a_cierre') === 'consumo') {
            return redirect()->route('backoffice.reserva.venta.cierre');
        }
        if ($request->input('ir_a_cierre') === '1' && is_null($venta->diferencia_programa)) {
            return redirect()->route('backoffice.reserva.venta.cerrar', ['reserva' => $venta->id_reserva, 'ventum' => $venta->id]);
        }
        return back();
    }
}
