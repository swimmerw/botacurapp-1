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

        return view('themes.backoffice.pages.reporte.reservas_dia', compact('fecha', 'reservas', 'abonosExtra', 'porMedio', 'resumen'));
    }
}
