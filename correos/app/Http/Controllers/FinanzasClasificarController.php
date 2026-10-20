<?php

namespace App\Http\Controllers;

use App\Finanzas\Correo\ConciliadorAbonos;
use App\Finanzas\Correo\ConciliadorSalidas;
use App\Finanzas\SueldoSemanas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Egresos › Clasificar: bandeja de los movimientos del banco que no se cruzaron solos.
 * Cada uno se asocia (reserva, sueldo, egreso), se registra como egreso con su categoría
 * o se clasifica (otro ingreso, aporte, personal...). Todo se puede deshacer.
 */
class FinanzasClasificarController extends Controller
{
    const ENTRADAS = ['otro_ingreso' => 'Otro ingreso', 'aporte_socio' => 'Aporte de socio', 'devolucion' => 'Devolución', 'personal' => 'Es personal'];
    const SALIDAS = ['retiro' => 'Retiro de socio', 'prestamo' => 'Préstamo', 'personal' => 'Es personal', 'no_es_gasto' => 'No es un gasto'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(FinanzasCorreoController::puedeVer(), 403);
            return $next($request);
        });
    }

    /** Qué movimientos van en cada pestaña de la bandeja. */
    private function consulta(string $tab)
    {
        $q = DB::table('fin_correos')->where('fecha', '>=', now()->subDays(90)->toDateString())
            ->whereNotIn('estado', ['sospechoso']);
        switch ($tab) {
            case 'entradas':
                return $q->where('tipo', 'abono')->whereIn('estado', ['nuevo', 'revisar']);
            case 'salidas':
                return $q->whereIn('tipo', ['pago', 'compra'])->whereIn('estado', ['nuevo', 'revisar']);
            case 'cuentas':
                return DB::table('fin_correos')->where('tipo', 'cuenta')->whereIn('estado', ['nuevo', 'revisar'])->whereNull('egreso_id');
            case 'tuyos':
                return $q->where('tipo', 'personal')->whereIn('estado', ['nuevo', 'revisar'])->whereNull('clasificacion');
            default: // listos: lo clasificado o asociado a mano, para revisar o deshacer
                return DB::table('fin_correos')->whereNotNull('clasificado_at');
        }
    }

    public function index(Request $request)
    {
        $personal = FinanzasCorreoController::puedeVerPersonal();
        $tabs = ['entradas' => 'Entradas', 'salidas' => 'Salidas', 'cuentas' => 'Cuentas por pagar'];
        if ($personal) {
            $tabs['tuyos'] = 'Desde tu cuenta';
        }
        $tabs['listos'] = 'Clasificados';
        $tab = array_key_exists($request->input('tab'), $tabs) ? $request->input('tab') : 'entradas';

        $conteos = [];
        foreach (array_keys($tabs) as $t) {
            if ($t !== 'listos') {
                $filas = $this->consulta($t)->get(['id', 'tipo', 'cuenta', 'buzon', 'monto']);
                if (in_array($t, ['entradas'], true)) {
                    $filas = $filas->filter(function ($c) {
                        return FinanzasCorreoController::ambito($c) === 'botacura';
                    });
                }
                $conteos[$t] = ['n' => $filas->count(), 'monto' => $filas->sum('monto')];
            }
        }

        $items = $this->consulta($tab)
            ->orderBy($tab === 'listos' ? 'clasificado_at' : 'fecha', 'desc')->orderByDesc('id')->limit(60)->get();
        if ($tab === 'entradas') {
            $items = $items->filter(function ($c) {
                return FinanzasCorreoController::ambito($c) === 'botacura';
            })->values();
        }
        if (!$personal) {
            $items = $items->reject(function ($c) {
                return in_array($c->tipo, ['personal', 'ajeno'], true);
            })->values();
        }

        $abonos = app(ConciliadorAbonos::class);
        $salidas = app(ConciliadorSalidas::class);
        $semanas = app(SueldoSemanas::class);
        foreach ($items as $c) {
            $c->opciones = [];
            $c->semanas = [];
            $c->persona = null;
            $c->sugerida = null;
            if ($tab === 'listos') {
                continue;
            }
            if ($c->tipo === 'abono') {
                $c->opciones = $abonos->candidatos($c);
                continue;
            }
            if (in_array($c->tipo, ['pago', 'compra', 'personal'], true)) {
                $c->opciones = array_slice($salidas->candidatos($c), 0, 4);
                $c->persona = $salidas->persona($c);
                if ($c->persona && $c->tipo === 'pago') {
                    $c->semanas = array_slice(array_values(array_filter($semanas->pendientes((int) $c->persona->id, $c->fecha ?: date('Y-m-d')), function ($w) {
                        return !$w['pagado'];
                    })), 0, 4);
                }
            }
            $c->sugerida = $this->categoriaSugerida($c);
        }

        $categorias = Schema::hasTable('categorias_compras')
            ? DB::table('categorias_compras')->orderBy('nombre')->get(['id', 'nombre']) : collect();
        $subcategorias = Schema::hasTable('subcategorias_compras')
            ? DB::table('subcategorias_compras')->orderBy('nombre')->get(['id', 'nombre', 'categoria_id']) : collect();
        $nombresCat = $categorias->pluck('nombre', 'id');
        $nombresSub = $subcategorias->pluck('nombre', 'id');

        return view('themes.backoffice.pages.finanzas_correos.clasificar', compact(
            'tabs', 'tab', 'conteos', 'items', 'categorias', 'subcategorias', 'nombresCat', 'nombresSub'
        ));
    }

    /**
     * Categoría que probablemente corresponde: la última usada con este mismo RUT o nombre,
     * o la subcategoría del proveedor registrado con ese RUT.
     */
    private function categoriaSugerida($c): ?array
    {
        $q = DB::table('fin_correos')->whereNotNull('categoria_id')->where('id', '<>', $c->id);
        $prev = null;
        if ($c->rut) {
            $prev = (clone $q)->where('rut', $c->rut)->orderByDesc('clasificado_at')->first();
        }
        if (!$prev && $c->nombre) {
            $prev = (clone $q)->where('nombre', $c->nombre)->orderByDesc('clasificado_at')->first();
        }
        if ($prev) {
            return ['categoria_id' => $prev->categoria_id, 'subcategoria_id' => $prev->subcategoria_id, 'por' => 'la vez anterior'];
        }
        $prov = $this->proveedor($c->rut, null, false);
        if ($prov && !empty($prov->subcategoria_id) && Schema::hasTable('subcategorias_compras')) {
            $sub = DB::table('subcategorias_compras')->where('id', $prov->subcategoria_id)->first();
            if ($sub) {
                return ['categoria_id' => $sub->categoria_id, 'subcategoria_id' => $sub->id, 'por' => 'el proveedor'];
            }
        }
        return null;
    }

    private function correo($id)
    {
        $c = DB::table('fin_correos')->where('id', $id)->first();
        abort_if(!$c, 404);
        abort_if(in_array($c->tipo, ['personal', 'ajeno'], true) && !FinanzasCorreoController::puedeVerPersonal(), 403);
        return $c;
    }

    private function marca(): array
    {
        return ['clasificado_por' => auth()->id(), 'clasificado_at' => now(), 'updated_at' => now()];
    }

    /** Entrada: asociar a una venta (reserva). */
    public function venta(Request $request, $id)
    {
        $request->validate(['venta_id' => 'required|integer', 'campo' => 'required|in:abono,diferencia,extra']);
        $c = $this->correo($id);
        abort_unless($c->tipo === 'abono' && DB::table('ventas')->where('id', $request->venta_id)->exists(), 404);
        DB::table('fin_correos')->where('id', $id)->update([
            'venta_id' => $request->venta_id, 'campo' => $request->campo, 'estado' => 'calzado', 'clasificacion' => 'reserva',
            'nota' => 'Asociado a la venta #' . $request->venta_id . ' por ' . auth()->user()->name,
        ] + $this->marca());
        return back()->with('success', 'Abono asociado a la venta #' . $request->venta_id . '.');
    }

    /** Entrada o salida: clasificar sin asociar (otro ingreso, aporte, retiro, personal...). */
    public function marcar(Request $request, $id)
    {
        $c = $this->correo($id);
        $permitidas = $c->tipo === 'abono' ? self::ENTRADAS : self::SALIDAS;
        $request->validate(['clasificacion' => 'required|in:' . implode(',', array_keys($permitidas))]);
        $cl = $request->clasificacion;
        DB::table('fin_correos')->where('id', $id)->update([
            'clasificacion' => $cl,
            'estado' => in_array($cl, ['no_es_gasto'], true) ? 'ignorado' : 'calzado',
            'vinculo' => $cl === 'retiro' ? 'retiro' : null,
            'nota' => mb_substr($permitidas[$cl] . ($request->filled('nota') ? ' · ' . $request->nota : '') . ' · ' . auth()->user()->name, 0, 191),
        ] + $this->marca());
        return back()->with('success', 'Clasificado como "' . $permitidas[$cl] . '".');
    }

    /** Salida o cuenta: registrar como egreso con su categoría (o solo guardar la categoría). */
    public function egreso(Request $request, $id)
    {
        $request->validate([
            'categoria_id' => 'required|integer', 'subcategoria_id' => 'nullable|integer',
            'proveedor' => 'nullable|string|max:120', 'descripcion' => 'nullable|string|max:190',
        ]);
        $c = $this->correo($id);
        abort_unless(in_array($c->tipo, ['pago', 'compra', 'personal', 'cuenta'], true), 404);
        abort_unless(DB::table('categorias_compras')->where('id', $request->categoria_id)->exists(), 422);

        $prov = $this->proveedor($c->rut, $request->input('proveedor') ?: ($c->proveedor ?: $c->nombre), true);
        $desc = $request->input('descripcion') ?: trim(($c->proveedor ?: $c->nombre) . ($c->comentario ? ' · ' . $c->comentario : ''));
        $esCuenta = $c->tipo === 'cuenta';
        $fecha = $c->fecha ?: substr((string) $c->recibido_at, 0, 10);

        DB::beginTransaction();
        try {
            $egresoId = $this->crearEgreso([
                'categoria_id' => (int) $request->categoria_id,
                'subcategoria_id' => $request->subcategoria_id ?: null,
                'proveedor_id' => $prov->id ?? null,
                'total' => (int) $c->monto,
                'descripcion' => mb_substr($desc, 0, 190),
                'fecha_egreso' => $fecha,
                'fecha' => $fecha,
                'estado' => $esCuenta ? 'pendiente' : 'pagado',
                'fuente' => 'banco',
                'observaciones' => '[correo #' . $c->id . '] ' . $c->banco . ' · ' . $c->asunto,
                'user_id' => auth()->id(),
            ]);
            $pagoId = null;
            if (!$esCuenta) {
                $pagoId = DB::table('pagos_egresos')->insertGetId([
                    'egreso_id' => $egresoId, 'folio' => $c->operacion ? mb_substr($c->operacion, 0, 60) : null,
                    'monto' => (int) $c->monto, 'fecha_pago' => $fecha, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('fin_correos')->where('id', $id)->update([
                'egreso_id' => $egresoId, 'pago_egreso_id' => $pagoId,
                'categoria_id' => (int) $request->categoria_id, 'subcategoria_id' => $request->subcategoria_id ?: null,
                'clasificacion' => 'egreso',
                'vinculo' => $c->tipo === 'personal' ? 'reembolso' : 'egreso',
                'estado' => $esCuenta ? $c->estado : 'calzado',
                'nota' => mb_substr('Egreso #' . $egresoId . ' · ' . ($prov->nombre ?? '') . ($c->tipo === 'personal' ? ' · Botacura te debe' : ''), 0, 191),
            ] + $this->marca());
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'No se pudo registrar el egreso: ' . $e->getMessage());
        }
        return back()->with('success', 'Registrado como egreso #' . $egresoId . '.');
    }

    /** Deshacer: vuelve a "por revisar". Borra el egreso solo si se creó desde esta bandeja. */
    public function deshacer($id)
    {
        $c = $this->correo($id);
        DB::beginTransaction();
        try {
            if ($c->egreso_id) {
                $e = DB::table('egresos')->where('id', $c->egreso_id)->first();
                if ($e && isset($e->observaciones) && strpos((string) $e->observaciones, '[correo #' . $c->id . ']') === 0) {
                    DB::table('pagos_egresos')->where('egreso_id', $e->id)->delete();
                    DB::table('egresos')->where('id', $e->id)->delete();
                }
            }
            DB::table('fin_correos')->where('id', $id)->update([
                'estado' => $c->tipo === 'personal' ? 'nuevo' : 'revisar',
                'clasificacion' => null, 'categoria_id' => null, 'subcategoria_id' => null,
                'vinculo' => null, 'egreso_id' => null, 'pago_egreso_id' => null, 'sueldo_pagado_id' => null,
                'venta_id' => null, 'campo' => null, 'abono_extra_id' => null, 'comprobante_id' => null,
                'nota' => 'Deshecho por ' . auth()->user()->name,
                'clasificado_por' => null, 'clasificado_at' => null, 'updated_at' => now(),
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'No se pudo deshacer: ' . $e->getMessage());
        }
        return back()->with('success', 'Listo, volvió a la bandeja.');
    }

    private function crearEgreso(array $datos): int
    {
        $cols = array_flip(Schema::getColumnListing('egresos'));
        $fila = array_intersect_key($datos, $cols) + array_intersect_key(['created_at' => now(), 'updated_at' => now()], $cols);
        return (int) DB::table('egresos')->insertGetId($fila);
    }

    /** Proveedor por RUT (o nombre exacto); si $crear y no existe, se crea. */
    private function proveedor(?string $rut, ?string $nombre, bool $crear)
    {
        if (!Schema::hasTable('proveedores')) {
            return null;
        }
        if ($rut) {
            $r = ConciliadorSalidas::rut($rut);
            foreach (DB::table('proveedores')->whereNotNull('rut')->get() as $p) {
                if (ConciliadorSalidas::rut($p->rut) === $r) {
                    return $p;
                }
            }
        }
        if ($nombre) {
            $p = DB::table('proveedores')->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($nombre))])->first();
            if ($p) {
                return $p;
            }
        }
        if (!$crear || !$nombre) {
            return null;
        }
        $id = DB::table('proveedores')->insertGetId(['nombre' => mb_substr(trim($nombre), 0, 190), 'rut' => $rut,
                                                    'created_at' => now(), 'updated_at' => now()]);
        return DB::table('proveedores')->where('id', $id)->first();
    }
}
