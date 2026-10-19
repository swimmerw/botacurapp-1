<?php

namespace App\Http\Controllers;

use App\Finanzas\Correo\ConciliadorAbonos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Finanzas > Correos bancarios: abonos recibidos (cruzados con reservas),
 * pagos hechos y cuentas por pagar que llegan al correo. Compatible Laravel 6 / PHP 7.2.
 */
class FinanzasCorreoController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(self::puedeVer(), 403);
            return $next($request);
        });
    }

    /** Solo los usuarios de FINANZAS_USUARIOS; si la lista está vacía, los administradores. */
    public static function puedeVer(): bool
    {
        $u = auth()->user();
        if (!$u) {
            return false;
        }
        $lista = config('finanzas.usuarios', []);
        if ($lista) {
            return in_array(strtolower($u->email), $lista, true);
        }
        return $u->has_role(config('app.admin_role'));
    }

    public function index(Request $request)
    {
        $tipo = $request->input('tipo', 'abono');
        $estado = $request->input('estado', 'todos');
        $desde = $request->input('desde', now()->subDays(14)->toDateString());

        $q = DB::table('fin_correos as c')
            ->leftJoin('ventas as v', 'v.id', '=', 'c.venta_id')
            ->leftJoin('reservas as r', 'r.id', '=', 'v.id_reserva')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'r.cliente_id')
            ->where('c.tipo', $tipo)
            ->where(function ($w) use ($desde) {
                $w->where('c.fecha', '>=', $desde)->orWhereNull('c.fecha');
            })
            ->when($estado !== 'todos', function ($w) use ($estado) {
                $w->where('c.estado', $estado);
            })
            ->orderByDesc('c.fecha')->orderByDesc('c.hora')->orderByDesc('c.id')
            ->select('c.*', 'r.id as reserva_id', 'r.fecha_visita', 'cl.nombre_cliente');
        $filas = $q->limit(500)->get();

        $conteo = DB::table('fin_correos')->where('fecha', '>=', $desde)
            ->select('tipo', 'estado', DB::raw('COUNT(*) n'), DB::raw('SUM(monto) total'))
            ->groupBy('tipo', 'estado')->get();
        $ultimo = DB::table('fin_correos')->max('created_at');

        return view('themes.backoffice.pages.finanzas_correos.index', compact('filas', 'tipo', 'estado', 'desde', 'conteo', 'ultimo'));
    }

    public function show($id)
    {
        $c = DB::table('fin_correos')->where('id', $id)->first();
        abort_if(!$c, 404);
        return view('themes.backoffice.pages.finanzas_correos.show', compact('c'));
    }

    /** Asociar a mano un abono con una venta (N° de venta o de reserva). */
    public function asociar(Request $request, $id)
    {
        $request->validate(['numero' => 'required|integer', 'que' => 'required|in:venta,reserva']);
        $c = DB::table('fin_correos')->where('id', $id)->first();
        abort_if(!$c, 404);
        $venta = $request->que === 'venta'
            ? DB::table('ventas')->where('id', $request->numero)->first()
            : DB::table('ventas')->where('id_reserva', $request->numero)->orderByDesc('id')->first();
        if (!$venta) {
            return back()->with('error', 'No encontré esa ' . $request->que . '.');
        }
        $campo = (int) $venta->diferencia_programa === (int) $c->monto && (int) $venta->abono_programa !== (int) $c->monto ? 'diferencia' : 'abono';
        DB::table('fin_correos')->where('id', $id)->update([
            'venta_id' => $venta->id, 'campo' => $campo, 'estado' => 'calzado',
            'nota' => 'Asociado a mano por ' . auth()->user()->name, 'updated_at' => now(),
        ]);
        return back()->with('success', "Abono asociado a la venta #{$venta->id}.");
    }

    public function estado(Request $request, $id)
    {
        $request->validate(['estado' => 'required|in:ignorado,revisar,pagado,nuevo']);
        $cambio = ['estado' => $request->estado, 'updated_at' => now()];
        if ($request->filled('nota')) {
            $cambio['nota'] = mb_substr($request->input('nota'), 0, 191);
        }
        DB::table('fin_correos')->where('id', $id)->update($cambio);
        return back()->with('success', 'Listo.');
    }

    /** Leer el correo ahora (además de la lectura automática cada 5 minutos). */
    public function leer()
    {
        Artisan::call('finanzas:leer-correos');
        Artisan::call('finanzas:leer-correos', ['--recruzar' => true]);
        return back()->with('success', 'Correo leído: ' . trim(Artisan::output()));
    }
}
