<?php

namespace App\Http\Controllers;

use App\Equipo\Turnos;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Equipo › Turnos sugeridos: propuesta de los próximos días según clientes, reglas y quién puede cada día. */
class EquipoTurnosController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $u = auth()->user();
            abort_unless(Turnos::hay() && $u && ($u->has_role(config('app.admin_role')) || $u->has_role(config('app.jefe_local_role'))), 403);
            return $next($request);
        });
    }

    public function index(Turnos $t)
    {
        $dias = [];
        for ($i = 1; $i <= 7; $i++) {
            $f = now('America/Santiago')->addDays($i)->toDateString();
            $x = $t->proponer($f);
            if (!$x['personas'] && !$x['habilitada']) {
                continue;
            }
            $x['guardada'] = DB::table('turno_propuestas')->where('fecha', $f)->first();
            $x['asignados'] = DB::table('asignaciones')->join('asignacion_user', 'asignacion_user.asignacion_id', '=', 'asignaciones.id')
                ->join('users', 'users.id', '=', 'asignacion_user.user_id')->where('asignaciones.fecha', $f)->pluck('users.name')->all();
            $dias[] = $x;
        }
        $equipo = $t->equipo()->sortBy(function ($u) { return array_search($u->area, array_keys(Turnos::AREAS)) . sprintf('%02d', $u->prioridad) . $u->name; })->values();
        $params = Turnos::parametros();
        return view('themes.backoffice.pages.equipo_turnos.index', compact('dias', 'equipo', 'params'));
    }

    public function aceptar(Request $request, Turnos $t, $fecha)
    {
        $fecha = Carbon::parse($fecha)->toDateString();
        $t->guardar($t->proponer($fecha));
        $n = $t->aceptar($fecha, auth()->id());
        return back()->with('success', "Equipo del " . Carbon::parse($fecha)->format('d-m') . " asignado ($n personas). Puedes ajustarlo en Turnos.");
    }

    public function avisar(Turnos $t)
    {
        \Artisan::call('equipo:turnos-manana', ['--enviar' => true, '--forzar' => true]);
        return back()->with('success', 'Aviso enviado: ' . mb_substr(trim(\Artisan::output()), -180));
    }

    public function guardarEquipo(Request $request)
    {
        foreach ((array) $request->input('p', []) as $uid => $v) {
            $area = array_key_exists($v['area'] ?? '', Turnos::AREAS) ? $v['area'] : 'no';
            $dias = implode(',', array_values(array_intersect(array_map('intval', (array) ($v['dias'] ?? [])), range(1, 7))));
            DB::table('equipo_config')->updateOrInsert(['user_id' => (int) $uid], [
                'area' => $area, 'dias' => $dias, 'prioridad' => max(1, min(9, (int) ($v['prioridad'] ?? 5))),
                'telefono' => preg_replace('/\D/', '', (string) ($v['telefono'] ?? '')) ?: null, 'updated_at' => now(),
            ]);
        }
        DB::table('equipo_config')->whereNull('created_at')->update(['created_at' => now()]);
        return back()->with('success', 'Equipo guardado.');
    }

    public function guardarReglas(Request $request)
    {
        foreach (array_keys(Turnos::parametrosDefault()) as $k) {
            if ($request->has($k)) {
                DB::table('equipo_parametros')->updateOrInsert(['clave' => $k], ['valor' => mb_substr(trim((string) $request->input($k)), 0, 191), 'updated_at' => now()]);
            }
        }
        return back()->with('success', 'Reglas guardadas.');
    }
}
