<?php

namespace App\Http\Controllers;

use App\Finanzas\Egresos\Categorizador;
use App\Finanzas\Egresos\Deudas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Por pagar y pagado: cuentas que llegan al correo, deudas y créditos, con cada abono.
 * Los avisos del banco se aplican solos; aquí se agregan, se abonan a mano, se corrigen o se sacan.
 */
class FinanzasDeudasController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(FinanzasCorreoController::puedeVer() && Deudas::hay(), 403);
            return $next($request);
        });
    }

    private function ambitos(): array
    {
        return FinanzasCorreoController::puedeVerPersonal() ? ['botacura', 'personal'] : ['botacura'];
    }

    public function index(Request $request)
    {
        $svc = app(Deudas::class);
        $pendientes = $svc->pendientes()->whereIn('ambito', $this->ambitos())->values();
        $pagadas = DB::table('fin_deudas')->where('estado', 'pagada')->whereIn('ambito', $this->ambitos())
            ->orderByDesc('updated_at')->limit(20)->get();
        $abonos = DB::table('fin_deuda_pagos as p')->join('fin_deudas as d', 'd.id', '=', 'p.deuda_id')
            ->whereIn('d.ambito', $this->ambitos())
            ->leftJoin('fin_correos as c', 'c.id', '=', 'p.correo_id')
            ->orderByDesc('p.fecha')->orderByDesc('p.id')->limit(40)
            ->get(['p.*', 'd.nombre as deuda', 'c.banco', 'c.nombre as destinatario']);
        $subcats = app(Categorizador::class)->subcategorias();
        $tot = ['saldo' => $pendientes->sum('saldo'), 'vencido' => $pendientes->where('vencida', true)->sum('saldo'),
                'mes' => DB::table('fin_deuda_pagos as p')->join('fin_deudas as d', 'd.id', '=', 'p.deuda_id')
                    ->whereIn('d.ambito', $this->ambitos())->where('p.fecha', '>=', now()->startOfMonth()->toDateString())->sum('p.monto')];
        return view('themes.backoffice.pages.egresos_panel.deudas', compact('pendientes', 'pagadas', 'abonos', 'subcats', 'tot'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data += ['estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()];
        DB::table('fin_deudas')->insert($data);
        return back()->with('success', 'Agregada a Por pagar.');
    }

    public function update(Request $request, $id)
    {
        $d = $this->deuda($id);
        DB::table('fin_deudas')->where('id', $d->id)->update($this->validar($request) + ['updated_at' => now()]);
        app(Deudas::class)->recalcular((int) $d->id);
        return back()->with('success', 'Actualizada.');
    }

    /** Abono a mano (efectivo, otra cuenta, ajuste). Si tapa el saldo, queda pagada. */
    public function pago(Request $request, $id)
    {
        $d = $this->deuda($id);
        $request->validate(['monto' => 'required', 'fecha' => 'required|date', 'medio' => 'nullable|string|max:40', 'nota' => 'nullable|string|max:191']);
        $monto = (int) preg_replace('/\D/', '', $request->monto);
        if ($monto <= 0) {
            return back()->with('error', 'Monto inválido.');
        }
        app(Deudas::class)->registrarPago($d, $monto, $request->fecha, $request->input('medio') ?: 'a mano', null, $request->nota);
        $saldo = app(Deudas::class)->saldo(DB::table('fin_deudas')->find($d->id));
        return back()->with('success', $saldo > 0 ? 'Abono registrado. Queda $' . number_format($saldo, 0, ',', '.') . '.' : "{$d->nombre} quedó pagada.");
    }

    /** "Ya está pagada": abona el saldo completo. */
    public function saldar(Request $request, $id)
    {
        $d = $this->deuda($id);
        $saldo = app(Deudas::class)->saldo($d);
        if ($saldo > 0) {
            app(Deudas::class)->registrarPago($d, $saldo, $request->input('fecha') ?: date('Y-m-d'), 'a mano', null, 'Marcada pagada por ' . auth()->user()->name);
        }
        return back()->with('success', "{$d->nombre} quedó pagada.");
    }

    /** Sacar un abono mal aplicado (vuelve a subir el saldo). */
    public function quitarPago($pagoId)
    {
        $p = DB::table('fin_deuda_pagos')->where('id', $pagoId)->first();
        abort_if(!$p, 404);
        $this->deuda($p->deuda_id);
        DB::table('fin_deuda_pagos')->where('id', $pagoId)->delete();
        if ($p->correo_id) {
            DB::table('fin_correos')->where('id', $p->correo_id)->where('vinculo', 'deuda')
                ->update(['estado' => 'revisar', 'vinculo' => null, 'nota' => 'Abono quitado de una deuda', 'updated_at' => now()]);
        }
        DB::table('fin_deudas')->where('id', $p->deuda_id)->where('estado', 'pagada')->update(['estado' => 'pendiente']);
        app(Deudas::class)->recalcular((int) $p->deuda_id);
        return back()->with('success', 'Abono quitado.');
    }

    public function anular($id)
    {
        $d = $this->deuda($id);
        DB::table('fin_deudas')->where('id', $d->id)->update(['estado' => $d->estado === 'anulada' ? 'pendiente' : 'anulada', 'updated_at' => now()]);
        if ($d->estado === 'anulada') {
            app(Deudas::class)->recalcular((int) $d->id);
        }
        return back()->with('success', $d->estado === 'anulada' ? 'Volvió a Por pagar.' : 'Sacada de Por pagar.');
    }

    private function deuda($id)
    {
        $d = DB::table('fin_deudas')->where('id', $id)->first();
        abort_if(!$d || !in_array($d->ambito, $this->ambitos(), true), 404);
        return $d;
    }

    private function validar(Request $request): array
    {
        $request->validate([
            'nombre' => 'required|string|max:150', 'tipo' => 'required|in:cuenta,deuda,credito',
            'monto_total' => 'required', 'acreedor' => 'nullable|string|max:150', 'rut' => 'nullable|string|max:20',
            'patron' => 'nullable|string|max:191', 'vencimiento' => 'nullable|date', 'cuota' => 'nullable',
            'subcategoria_id' => 'nullable|integer', 'ambito' => 'nullable|in:botacura,personal', 'nota' => 'nullable|string|max:191',
        ]);
        $subs = app(Categorizador::class)->subcategorias();
        $sub = $request->subcategoria_id && isset($subs[(int) $request->subcategoria_id]) ? (int) $request->subcategoria_id : null;
        if (!$sub) {
            $auto = app(Categorizador::class)->paraNombre(trim($request->nombre . ' ' . $request->acreedor . ' ' . $request->patron));
            $sub = $auto['subcategoria_id'] ?? null;
        }
        $ambito = in_array($request->input('ambito', 'botacura'), $this->ambitos(), true) ? $request->input('ambito', 'botacura') : 'botacura';
        return [
            'nombre' => $request->nombre, 'tipo' => $request->tipo, 'acreedor' => $request->acreedor, 'rut' => $request->rut,
            'patron' => $request->patron ? mb_strtolower($request->patron) : null, 'ambito' => $ambito,
            'monto_total' => (int) preg_replace('/\D/', '', $request->monto_total),
            'cuota' => $request->cuota ? (int) preg_replace('/\D/', '', $request->cuota) : null,
            'vencimiento' => $request->vencimiento ?: null, 'nota' => $request->nota,
            'subcategoria_id' => $sub, 'categoria_id' => $sub ? $subs[$sub]['cat_id'] : null,
        ];
    }
}
