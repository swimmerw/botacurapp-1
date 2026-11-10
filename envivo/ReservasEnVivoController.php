<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Reservas en vivo (10/11/2026): las reservas que se van creando (bot, web y backoffice) con la vista
 * previa del comprobante de pago, los cupos apartados que esperan comprobante y los comprobantes sin reserva.
 */
class ReservasEnVivoController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->check(), 403);
        $dias = (int) $request->input('dias', 1);
        if (!in_array($dias, [1, 3, 7, 14], true)) { $dias = 1; }
        $fuente = (string) $request->input('fuente', '');
        $desde = $dias === 1 ? now()->startOfDay() : now()->subDays($dias);

        $q = DB::table('reservas as r')->leftJoin('clientes as c', 'c.id', '=', 'r.cliente_id')
            ->leftJoin('programas as p', 'p.id', '=', 'r.id_programa')->leftJoin('ventas as v', 'v.id_reserva', '=', 'r.id')
            ->where('r.created_at', '>=', $desde)
            ->select('r.id', 'r.created_at', 'r.fecha_visita', 'r.cantidad_personas', 'r.estado', 'r.fuente', 'r.observacion',
                'c.nombre_cliente', 'c.whatsapp_cliente', 'p.nombre_programa',
                'v.id as venta_id', 'v.abono_programa', 'v.diferencia_programa', 'v.estado_pago', 'v.comprobante_transferencia',
                'v.comprobante_monto', 'v.comprobante_fecha', 'v.comprobante_hora', 'v.comprobante_numero_operacion',
                'v.comprobante_nombre_origen', 'v.comprobante_alerta')
            ->orderBy('r.created_at', 'desc');
        if ($fuente !== '') { $fuente === 'bot' ? $q->where('r.fuente', 'like', 'bot%') : $q->where('r.fuente', $fuente); }
        $reservas = $q->limit(300)->get();

        $holds = DB::table('reserva_holds')->where('estado', 'activo')->orderBy('expira_en')->get();
        $sinReserva = Schema::hasTable('comprobantes_sin_reserva') ? DB::table('comprobantes_sin_reserva')->where('estado', 'pendiente')->count() : 0;
        $fuentes = DB::table('reservas')->where('created_at', '>=', $desde)->select('fuente', DB::raw('COUNT(*) n'))->groupBy('fuente')->pluck('n', 'fuente');

        $resumen = [
            'reservas'   => $reservas->count(),
            'personas'   => $reservas->sum('cantidad_personas'),
            'abonos'     => $reservas->sum('abono_programa'),
            'porVerificar' => $reservas->where('estado_pago', 'pendiente_verificacion')->count(),
            'sinComprobante' => $reservas->filter(function ($r) { return !$r->comprobante_transferencia && $r->estado_pago !== 'pagado'; })->count(),
        ];
        return view('themes.backoffice.pages.reservas-en-vivo.index', compact('reservas', 'holds', 'sinReserva', 'resumen', 'dias', 'fuente', 'fuentes'));
    }

    public function comprobante($ventaId)
    {
        $v = DB::table('ventas')->where('id', $ventaId)->first();
        abort_if(!$v || !$v->comprobante_transferencia || !Storage::disk('comprobante_transferencia')->exists($v->comprobante_transferencia), 404);
        return Storage::disk('comprobante_transferencia')->response($v->comprobante_transferencia);
    }
}
