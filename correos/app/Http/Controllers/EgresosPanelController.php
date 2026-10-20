<?php

namespace App\Http\Controllers;

use App\Finanzas\Egresos\Categorizador;
use App\Finanzas\Egresos\Deudas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Egresos del mes (reemplaza /egreso/{anio}/{mes}): todo lo gastado, por categoría, con su cuadre
 * (documento + pago), lo que falta clasificar y lo que hay por pagar. Se arma solo con:
 * facturas del SII (egresos), avisos del banco (fin_correos), remuneraciones (sueldos_pagados) y deudas.
 */
class EgresosPanelController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(FinanzasCorreoController::puedeVer(), 403);
            return $next($request);
        });
    }

    public function mes($anio, $mes)
    {
        $desde = Carbon::create((int) $anio, (int) $mes, 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();
        $d = $this->datos($desde, $hasta, true);
        $prev = $this->datos($desde->copy()->subMonth()->startOfMonth(), $desde->copy()->subMonth()->endOfMonth(), false);

        $deudas = Deudas::hay() ? app(Deudas::class)->pendientes('botacura') : collect();
        $vencidas = $deudas->where('vencida', true);

        $faltan = [];
        if ($d['facturas_sin_pago']->count()) {
            $faltan[] = ['n' => $d['facturas_sin_pago']->count(), 'txt' => 'facturas sin pago encontrado', 'href' => '#cuadre'];
        }
        if ($d['por_clasificar']['n']) {
            $faltan[] = ['n' => $d['por_clasificar']['n'], 'txt' => 'movimientos del banco por clasificar', 'href' => route('backoffice.finanzas.clasificar.index', ['tab' => 'salidas'])];
        }
        if ($d['sin_categoria']['n']) {
            $faltan[] = ['n' => $d['sin_categoria']['n'], 'txt' => 'gastos sin categoría', 'href' => '#sin-categoria'];
        }
        if ($vencidas->count()) {
            $faltan[] = ['n' => $vencidas->count(), 'txt' => 'cuentas vencidas', 'href' => route('backoffice.finanzas.deudas.index')];
        }

        $nombreMes = ucfirst($desde->locale('es')->isoFormat('MMMM YYYY'));
        $anterior = $desde->copy()->subMonth();
        $siguiente = $desde->copy()->addMonth();
        $categoriasNuevas = app(Categorizador::class)->subcategorias();

        return view('themes.backoffice.pages.egresos_panel.mes', compact(
            'd', 'prev', 'deudas', 'faltan', 'nombreMes', 'anterior', 'siguiente', 'anio', 'mes', 'categoriasNuevas'
        ));
    }

    /** Todo el mes: totales por categoría y las listas del cuadre. */
    private function datos(Carbon $desde, Carbon $hasta, bool $detalle): array
    {
        $cat = app(Categorizador::class);
        $subs = $cat->subcategorias();
        $ini = $desde->toDateString();
        $fin = $hasta->toDateString();
        $arbol = [];
        $agregar = function ($r, string $quien, int $monto) use (&$arbol, $subs) {
            $c = $r ? $subs[$r['subcategoria_id']]['cat'] : 'Sin categoría';
            $s = $r ? $subs[$r['subcategoria_id']]['sub'] : 'Por categorizar';
            if (!isset($arbol[$c])) {
                $arbol[$c] = ['total' => 0, 'subs' => []];
            }
            if (!isset($arbol[$c]['subs'][$s])) {
                $arbol[$c]['subs'][$s] = ['total' => 0, 'quien' => []];
            }
            $arbol[$c]['total'] += $monto;
            $arbol[$c]['subs'][$s]['total'] += $monto;
            $arbol[$c]['subs'][$s]['quien'][$quien] = ($arbol[$c]['subs'][$s]['quien'][$quien] ?? 0) + $monto;
        };

        // 1. Egresos registrados (SII, manuales, medidores, bandeja)
        $hayCorreos = Schema::hasTable('fin_correos') && Schema::hasColumn('fin_correos', 'egreso_id');
        $q = DB::table('egresos as e')->leftJoin('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->whereBetween('e.fecha_egreso', [$ini, $fin]);
        if (Schema::hasColumn('egresos', 'reconciliado_con_id')) {
            $q->whereNull('e.reconciliado_con_id');
        }
        $cols = ['e.id', 'e.total', 'e.fecha_egreso', 'e.categoria_id', 'e.subcategoria_id', 'e.proveedor_id', 'p.nombre as proveedor_nombre'];
        foreach (['descripcion', 'fuente', 'tipo_documento_id', 'numero_documento'] as $col) {
            $cols[] = Schema::hasColumn('egresos', $col) ? 'e.' . $col : DB::raw("NULL as {$col}");
        }
        $egresos = $q->select($cols)->get();
        $conPago = DB::table('pagos_egresos')->whereIn('egreso_id', $egresos->pluck('id'))->pluck('egreso_id')->flip();
        $conAviso = $hayCorreos
            ? DB::table('fin_correos')->whereIn('egreso_id', $egresos->pluck('id'))->where('estado', 'calzado')->pluck('egreso_id')->flip()
            : collect();
        $facturasSinPago = collect();
        $sinCategoria = ['n' => 0, 'total' => 0, 'proveedores' => []];
        $cuadrados = 0;
        foreach ($egresos as $e) {
            $r = $cat->paraEgreso($e);
            $quien = $e->proveedor_nombre ?: ($e->descripcion ?: 'Sin proveedor');
            $agregar($r, $quien, (int) $e->total);
            $pagado = isset($conPago[$e->id]) || isset($conAviso[$e->id]);
            $cuadrados += $pagado ? 1 : 0;
            if (!$pagado && ($e->fuente === 'sii' || $e->tipo_documento_id)) {
                $facturasSinPago->push($e);
            }
            if (!$r) {
                $sinCategoria['n']++;
                $sinCategoria['total'] += (int) $e->total;
                $k = $e->proveedor_id ?: 0;
                if (!isset($sinCategoria['proveedores'][$k])) {
                    $sinCategoria['proveedores'][$k] = ['proveedor_id' => $e->proveedor_id, 'nombre' => $quien, 'n' => 0, 'total' => 0];
                }
                $sinCategoria['proveedores'][$k]['n']++;
                $sinCategoria['proveedores'][$k]['total'] += (int) $e->total;
            }
        }

        // 2. Remuneraciones pagadas en el mes
        $sueldos = Schema::hasTable('sueldos_pagados')
            ? (int) DB::table('sueldos_pagados')->whereBetween('fecha_pago', [$ini, $fin])->sum('monto') : 0;
        if ($sueldos) {
            $sub = collect($subs)->filter(function ($s) {
                return $s['cat'] === 'Personas' && $s['sub'] === 'Sueldos del equipo';
            })->keys()->first();
            $agregar($sub ? ['subcategoria_id' => $sub] : null, 'Remuneraciones', $sueldos);
        }

        // 3. Pagos del banco sin documento (ya cruzados o clasificados, sin egreso ni sueldo detrás)
        $sinFactura = collect();
        $porClasificar = ['n' => 0, 'total' => 0];
        $fuera = ['retiros' => 0, 'traspasos' => 0];
        if ($hayCorreos) {
            $movs = DB::table('fin_correos')->whereBetween('fecha', [$ini, $fin])
                ->whereIn('tipo', ['pago', 'compra', 'personal', 'retiro', 'traspaso'])->whereNotIn('estado', ['ignorado', 'sospechoso'])->get();
            $deudasCuenta = Deudas::hay() ? DB::table('fin_deudas')->where('tipo', 'cuenta')->pluck('id')->flip() : collect();
            $pagosDeuda = Deudas::hay() ? DB::table('fin_deuda_pagos')->whereIn('correo_id', $movs->pluck('id'))->pluck('deuda_id', 'correo_id') : collect();
            foreach ($movs as $m) {
                if ($m->tipo === 'retiro' || $m->vinculo === 'retiro' || $m->clasificacion === 'retiro') {
                    $fuera['retiros'] += (int) $m->monto;
                    continue;
                }
                if ($m->tipo === 'traspaso') {
                    $fuera['traspasos'] += (int) $m->monto;
                    continue;
                }
                if (in_array($m->estado, ['nuevo', 'revisar'], true)) {
                    if ($m->tipo !== 'personal') {
                        $porClasificar['n']++;
                        $porClasificar['total'] += (int) $m->monto;
                    }
                    continue;
                }
                // Ya contados por otro lado o que no son gasto de Botacura
                if ($m->egreso_id || $m->pago_egreso_id || $m->sueldo_pagado_id
                    || in_array($m->clasificacion, ['personal', 'prestamo', 'no_es_gasto'], true)
                    || ($m->tipo === 'personal' && $m->vinculo !== 'reembolso' && $m->vinculo !== 'deuda')) {
                    continue;
                }
                // Abonos a deudas: solo las cuentas (luz, teléfono…) son gasto del mes
                if ($m->vinculo === 'deuda' && !isset($deudasCuenta[$pagosDeuda[$m->id] ?? -1])) {
                    continue;
                }
                $r = $m->categoria_id && $cat->esNueva((int) $m->subcategoria_id)
                    ? ['subcategoria_id' => (int) $m->subcategoria_id]
                    : $cat->paraNombre(trim($m->nombre . ' ' . $m->proveedor));
                $agregar($r, $m->nombre ?: ($m->proveedor ?: 'Banco'), (int) $m->monto);
                $sinFactura->push($m);
            }
        }

        $total = array_sum(array_column($arbol, 'total'));
        uasort($arbol, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        if (isset($arbol['Sin categoría'])) { // al final
            $x = $arbol['Sin categoría'];
            unset($arbol['Sin categoría']);
            $arbol['Sin categoría'] = $x;
        }
        foreach ($arbol as &$c) {
            uasort($c['subs'], function ($a, $b) {
                return $b['total'] <=> $a['total'];
            });
            foreach ($c['subs'] as &$s) {
                arsort($s['quien']);
            }
        }
        unset($c, $s);

        return [
            'total' => $total,
            'arbol' => $detalle ? $arbol : [],
            'egresos_n' => $egresos->count(),
            'cuadrados' => $cuadrados,
            'facturas_sin_pago' => $facturasSinPago->sortByDesc('total')->values(),
            'sin_factura' => $sinFactura->sortByDesc('monto')->values(),
            'por_clasificar' => $porClasificar,
            'sin_categoria' => $sinCategoria,
            'sueldos' => $sueldos,
            'fuera' => $fuera,
        ];
    }

    /** Asignar categoría a un proveedor desde el panel: queda como regla y se aplica a sus egresos. */
    public function categorizarProveedor(Request $request)
    {
        $request->validate(['proveedor_id' => 'required|integer', 'subcategoria_id' => 'required|integer']);
        $cat = app(Categorizador::class);
        abort_unless($cat->esNueva((int) $request->subcategoria_id), 422);
        $sub = $cat->subcategorias()[(int) $request->subcategoria_id];
        $cat->aprender((int) $request->proveedor_id, (int) $request->subcategoria_id);
        $n = DB::table('egresos')->where('proveedor_id', $request->proveedor_id)
            ->update(['categoria_id' => $sub['cat_id'], 'subcategoria_id' => (int) $request->subcategoria_id]);
        return back()->with('success', "{$n} egresos pasaron a {$sub['cat']} › {$sub['sub']}. Los próximos de ese proveedor llegan ya clasificados.");
    }
}
