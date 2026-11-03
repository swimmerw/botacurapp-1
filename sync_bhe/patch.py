import sys, os
# Remuneraciones: botón "Sincronizar boletas SII" (solo administradores).
#  - Ruta POST sueldos/sincronizar-honorarios (antes del resource, igual que el CSV).
#  - SueldoController@sincronizarHonorarios: un solo login al SII, trae las BHE de los meses
#    que tocan las semanas del mes que se está viendo (incluye el mes vecino cuando la semana
#    cruza de mes, ej. 26/10–01/11) y vuelve a la misma vista con el resultado.
#  - Vista: botón + fecha de la última sincronización.
base = sys.argv[1]
hechos = []

def editar(rel, fn):
    p = os.path.join(base, rel)
    s = open(p, encoding='utf-8').read()
    n = fn(s)
    if n != s:
        open(p, 'w', encoding='utf-8').write(n)
        hechos.append(rel.split('/')[-1])

def uno(s, a, nuevo, quien):
    if s.count(a) != 1:
        sys.exit(f'{quien}: esperaba 1 vez "{a.strip()[:70]}" y aparece {s.count(a)}')
    return s.replace(a, nuevo, 1)

RUTA_ANCLA = "    Route::get('sueldos/exportar-csv-semana', 'SueldoController@exportarCsvSemana')->name('sueldos.exportar-csv-semana');\n"
RUTA = RUTA_ANCLA + "    Route::post('sueldos/sincronizar-honorarios', 'SueldoController@sincronizarHonorarios')->name('sueldos.sincronizar-honorarios');\n"

def rutas(s):
    if 'sueldos.sincronizar-honorarios' in s:
        return s
    return uno(s, RUTA_ANCLA, RUTA, 'routes/web.php')

METODO = r'''    /**
     * Botón "Sincronizar boletas SII" de Remuneraciones.
     * Trae las BHE del SII para los meses que tocan las semanas del mes que se está viendo
     * (una semana que cruza de mes, como 26/10–01/11, también trae el mes vecino).
     */
    public function sincronizarHonorarios(Request $request, \App\Services\SiiBteService $bte)
    {
        if (!Auth::user()->has_role(config('app.admin_role'))) {
            abort(403);
        }

        $mes  = (int) $request->input('mes', now()->month);
        $anio = (int) $request->input('anio', now()->year);
        $volver = redirect()->route('backoffice.sueldos.index', ['mes' => $mes, 'anio' => $anio]);

        if (!$bte->credencialesConfiguradas()) {
            return $volver->with('error', 'Faltan las credenciales del SII en el .env.');
        }

        // Desde el lunes de la primera semana hasta el domingo de la última, sin pasar de hoy
        $desde = Carbon::create($anio, $mes, 1)->startOfWeek(Carbon::MONDAY);
        $hasta = Carbon::create($anio, $mes, 1)->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        if ($hasta->gt(now())) {
            $hasta = now();
        }
        $porAnio = [];
        for ($d = $desde->copy()->startOfMonth(); $d->lte($hasta); $d->addMonth()) {
            $porAnio[$d->year][] = $d->month;
        }

        @set_time_limit(180);
        $nuevas = 0;
        $actualizadas = 0;
        $encontradas = 0;
        $errores = [];
        $ahora = now();

        foreach ($porAnio as $a => $meses) {
            foreach ($bte->consultarMultiplesMeses($a, $meses, 2) as $periodo => $r) {
                if (!$r['ok']) {
                    $errores[] = $periodo . ': ' . $r['error'];
                    continue;
                }
                DB::beginTransaction();
                try {
                    foreach ($r['data'] as $b) {
                        $encontradas++;
                        $existe = HonorarioBte::where('folio', $b['folio'])->where('rut_emisor', $b['rut_emisor'])->first();
                        if ($existe) {
                            $existe->update(array_merge($b, ['sincronizado_at' => $ahora]));
                            $actualizadas++;
                        } else {
                            $prov = \App\Proveedor::where('rut', $b['rut_emisor'])->first();
                            HonorarioBte::create(array_merge($b, [
                                'proveedor_id'    => $prov ? $prov->id : null,
                                'sincronizado_at' => $ahora,
                            ]));
                            $nuevas++;
                        }
                    }
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $errores[] = $periodo . ': ' . $e->getMessage();
                }
            }
        }

        $periodos = [];
        foreach ($porAnio as $a => $meses) {
            foreach ($meses as $m) {
                $periodos[] = sprintf('%02d/%d', $m, $a);
            }
        }
        $msg = 'Boletas SII (' . implode(', ', $periodos) . "): {$encontradas} encontradas, {$nuevas} nuevas, {$actualizadas} actualizadas.";

        if ($errores) {
            return $volver->with('error', $msg . ' Error en ' . implode(' | ', $errores));
        }
        return $volver->with('success', $msg);
    }

    public function index(Request $request)
'''

def controlador(s):
    if 'function sincronizarHonorarios' in s:
        return s
    s = uno(s, "    public function index(Request $request)\n", METODO, 'SueldoController')
    if 'use Illuminate\\Support\\Facades\\Auth;' not in s:
        s = uno(s, "use Illuminate\\Support\\Facades\\DB;\n", "use Illuminate\\Support\\Facades\\Auth;\nuse Illuminate\\Support\\Facades\\DB;\n", 'SueldoController (use Auth)')
    return s

BOTON = '''                    @if(Auth::user()->has_role(config('app.admin_role')))
                        @php $ultimaBhe = \\App\\HonorarioBte::max('sincronizado_at'); @endphp
                        <div class="row" style="margin-bottom:0;">
                            <div class="col s12 right-align">
                                <form method="POST" action="{{ route('backoffice.sueldos.sincronizar-honorarios') }}" style="display:inline;"
                                      onsubmit="var b=this.querySelector('button'); b.disabled=true; b.innerHTML='<i class=&quot;material-icons left&quot;>sync</i>Consultando SII…';">
                                    @csrf
                                    <input type="hidden" name="mes" value="{{ $mes }}">
                                    <input type="hidden" name="anio" value="{{ $anio }}">
                                    <button type="submit" class="btn waves-effect waves-light" style="background:#039B7B;"
                                            title="Trae del SII las boletas de honorarios de las semanas de este mes y actualiza la columna Boleta">
                                        <i class="material-icons left">sync</i>Sincronizar boletas SII
                                    </button>
                                </form>
                                <div class="grey-text" style="font-size:11px; margin-top:4px;">
                                    Última sincronización: {{ $ultimaBhe ? \\Carbon\\Carbon::parse($ultimaBhe)->format('d/m/Y H:i') : 'nunca' }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @php
                        $sueldoMes = 0;'''

def vista(s):
    if 'sueldos.sincronizar-honorarios' in s:
        return s
    return uno(s, "                    @php\n                        $sueldoMes = 0;", BOTON, 'sueldo/index.blade.php')

editar('routes/web.php', rutas)
editar('app/Http/Controllers/SueldoController.php', controlador)
editar('resources/views/themes/backoffice/pages/sueldo/index.blade.php', vista)
print('Sincronizar BHE: ' + (', '.join(hechos) if hechos else 'ya estaba'))
