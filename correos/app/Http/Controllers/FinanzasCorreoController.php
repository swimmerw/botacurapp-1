<?php

namespace App\Http\Controllers;

use App\Finanzas\Correo\ConciliadorAbonos;
use App\Finanzas\Correo\ConciliadorSalidas;
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

    const PERSONALES = ['personal', 'ajeno'];

    /** Los gastos personales los ve solo Sebastián (FINANZAS_PERSONAL; por defecto el primero de FINANZAS_USUARIOS). */
    public static function puedeVerPersonal(): bool
    {
        $u = auth()->user();
        $lista = config('finanzas.personal') ?: array_slice(config('finanzas.usuarios', []), 0, 1);
        return $u && in_array(strtolower($u->email), $lista, true);
    }

    public function index(Request $request)
    {
        $tipo = $request->input('tipo', 'abono');
        abort_if(in_array($tipo, self::PERSONALES, true) && !self::puedeVerPersonal(), 403);
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
            ->when(!self::puedeVerPersonal(), function ($w) {
                $w->whereNotIn('tipo', self::PERSONALES);
            })
            ->select('tipo', 'estado', DB::raw('COUNT(*) n'), DB::raw('SUM(monto) total'))
            ->groupBy('tipo', 'estado')->get();
        $ultimo = DB::table('fin_correos')->max('created_at');

        return view('themes.backoffice.pages.finanzas_correos.index', compact('filas', 'tipo', 'estado', 'desde', 'conteo', 'ultimo'));
    }

    public function show($id)
    {
        $c = DB::table('fin_correos')->where('id', $id)->first();
        abort_if(!$c, 404);
        abort_if(in_array($c->tipo, self::PERSONALES, true) && !self::puedeVerPersonal(), 403);
        $candidatos = [];
        $persona = null;
        if (in_array($c->tipo, ['pago', 'compra', 'personal'], true) && \Illuminate\Support\Facades\Schema::hasColumn('fin_correos', 'vinculo')) {
            $s = app(ConciliadorSalidas::class);
            $candidatos = array_slice($s->candidatos($c), 0, 6);
            $persona = $s->persona($c);
        }
        $semanas = ($persona && $c->tipo === 'pago')
            ? array_slice(array_values(array_filter(app(\App\Finanzas\SueldoSemanas::class)->pendientes((int) $persona->id, $c->fecha ?: date('Y-m-d')), function ($w) {
                return !$w['pagado'];
            })), 0, 6)
            : [];
        $relacionado = !empty($c->correo_id) ? DB::table('fin_correos')->where('id', $c->correo_id)->first() : null;
        return view('themes.backoffice.pages.finanzas_correos.show', compact('c', 'candidatos', 'persona', 'relacionado', 'semanas'));
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

    /** Asociar a mano una salida con un sueldo pagado o con el pago de un egreso. */
    public function vincular(Request $request, $id)
    {
        $request->validate(['clase' => 'required|in:sueldo,egreso', 'ref' => 'required|integer']);
        $c = DB::table('fin_correos')->where('id', $id)->first();
        abort_if(!$c || !in_array($c->tipo, ['pago', 'compra', 'personal'], true), 404);
        $tabla = $request->clase === 'sueldo' ? 'sueldos_pagados' : 'pagos_egresos';
        abort_unless(DB::table($tabla)->where('id', $request->ref)->exists(), 404);
        DB::table('fin_correos')->where('id', $id)->update([
            'estado' => 'calzado',
            'vinculo' => $c->tipo === 'personal' ? 'reembolso' : $request->clase,
            'sueldo_pagado_id' => $request->clase === 'sueldo' ? $request->ref : null,
            'pago_egreso_id' => $request->clase === 'egreso' ? $request->ref : null,
            'nota' => mb_substr(($request->input('label') ?: ucfirst($request->clase) . ' #' . $request->ref) . ' · asociado por ' . auth()->user()->name, 0, 191),
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Salida asociada.');
    }

    /** Marca a mano una semana de remuneración como pagada con esta transferencia. */
    public function pagarSemana(Request $request, $id)
    {
        $request->validate(['inicio' => 'required|date', 'fin' => 'required|date']);
        $c = DB::table('fin_correos')->where('id', $id)->first();
        abort_if(!$c || $c->tipo !== 'pago', 404);
        abort_unless(auth()->user()->has_role(config('app.admin_role')), 403);
        $persona = app(ConciliadorSalidas::class)->persona($c);
        abort_if(!$persona, 422, 'No sé a qué funcionario va este pago.');
        $sem = collect(app(\App\Finanzas\SueldoSemanas::class)->pendientes((int) $persona->id, $c->fecha ?: date('Y-m-d')))
            ->first(function ($w) use ($request) {
                return $w['inicio'] === $request->inicio && $w['fin'] === $request->fin && !$w['pagado'];
            });
        if (!$sem) {
            return back()->with('error', 'Esa semana ya está pagada o no tiene días trabajados.');
        }
        $spId = app(\App\Finanzas\SueldoSemanas::class)->marcarPagada((int) $persona->id, $sem, $c->fecha ?: date('Y-m-d'));
        DB::table('fin_correos')->where('id', $id)->update([
            'estado' => 'calzado', 'vinculo' => 'sueldo', 'sueldo_pagado_id' => $spId,
            'nota' => mb_substr("Remuneración de {$persona->name}, {$sem['etiqueta']} · marcada por " . auth()->user()->name, 0, 191),
            'updated_at' => now(),
        ]);
        return back()->with('success', "{$sem['etiqueta']} de {$persona->name} quedó Pagada en Remuneraciones.");
    }

    /**
     * Movimientos: entradas y salidas avisadas por los bancos, día por día, con su cruce.
     * Ámbito "botacura" (cuentas de la empresa) o "personal" (solo quien ve lo personal).
     */
    public function movimientos(Request $request)
    {
        $ambito = $request->input('ambito', 'botacura');
        abort_if($ambito === 'personal' && !self::puedeVerPersonal(), 403);
        $desde = $request->input('desde', now()->subDays(6)->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());

        $tipos = ['abono', 'pago', 'retiro', 'traspaso', 'compra', 'personal'];
        $filas = DB::table('fin_correos as c')
            ->leftJoin('ventas as v', 'v.id', '=', 'c.venta_id')
            ->leftJoin('reservas as r', 'r.id', '=', 'v.id_reserva')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'r.cliente_id')
            ->whereIn('c.tipo', $tipos)->whereNotIn('c.estado', ['ignorado', 'sospechoso'])
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->orderByDesc('c.fecha')->orderByDesc('c.hora')->orderByDesc('c.id')
            ->select('c.*', 'r.id as reserva_id', 'r.fecha_visita', 'cl.nombre_cliente')
            ->limit(1500)->get()
            ->filter(function ($c) use ($ambito) {
                return self::ambito($c) === $ambito;
            })->values();

        $entra = function ($c) {
            return $c->tipo === 'abono';
        };
        $res = [
            'entradas'   => $filas->filter($entra)->sum('monto'),
            'salidas'    => $filas->reject($entra)->where('tipo', '!=', 'traspaso')->sum('monto'),
            'abonos_ok'  => $filas->where('tipo', 'abono')->where('estado', 'calzado')->count(),
            'abonos_rev' => $filas->where('tipo', 'abono')->where('estado', '!=', 'calzado')->count(),
            'sin_reg'    => $filas->whereIn('tipo', ['pago', 'compra'])->where('estado', '!=', 'calzado'),
            'retiros'    => $filas->where('tipo', 'retiro')->groupBy('nombre')->map(function ($g) {
                return $g->sum('monto');
            }),
            'reembolsos' => self::puedeVerPersonal()
                ? DB::table('fin_correos')->where('vinculo', 'reembolso')->where('estado', 'calzado')->whereBetween('fecha', [$desde, $hasta])->get()
                : collect(),
        ];
        $cuentas = DB::table('fin_correos')->where('tipo', 'cuenta')->whereIn('estado', ['nuevo', 'revisar'])
            ->orderByRaw('vencimiento IS NULL')->orderBy('vencimiento')->limit(30)->get();
        $dias = $filas->groupBy('fecha');

        return view('themes.backoffice.pages.finanzas_correos.movimientos',
            compact('dias', 'res', 'cuentas', 'ambito', 'desde', 'hasta'));
    }

    /** ¿El movimiento es de las cuentas de Botacura o de la cuenta personal? */
    public static function ambito($c): string
    {
        if (in_array($c->tipo, ['personal', 'ajeno'], true)) {
            return 'personal';
        }
        if ($c->tipo !== 'abono') {
            return 'botacura'; // pagos, compras, traspasos y retiros salen de Botacura
        }
        $nombre = null;
        foreach ((array) config('finanzas.cuentas') as $num => $n) {
            if ($c->cuenta && ltrim((string) $num, '0') === ltrim((string) $c->cuenta, '0')) {
                $nombre = $n;
            }
        }
        if ($nombre) {
            return stripos($nombre, 'botacura') !== false ? 'botacura' : 'personal';
        }
        return (empty($c->buzon) || stripos($c->buzon, 'botacura') !== false) ? 'botacura' : 'personal';
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
