<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Postres del día en la vista Menús: cocina (y administración / jefe de local) ingresa
 * el nombre del postre y cuántos preparó. Todos los que ven Menús ven la lista.
 */
class PostreDiaController extends Controller
{
    /** Perfiles que pueden ingresar y editar postres. */
    public static function puede(): bool
    {
        $u = Auth::user();
        return $u && ($u->has_role(config('app.cocina_role')) || $u->has_role(config('app.admin_role'))
            || $u->has_role(config('app.jefe_local_role')));
    }

    /** Postres de una fecha (Y-m-d) y nombres usados antes, para el JSON de la vista. */
    public static function delDia(string $fechaISO): array
    {
        if (!Schema::hasTable('postres_dia')) {
            return ['postres' => [], 'sugeridos' => [], 'puede' => false];
        }
        $postres = DB::table('postres_dia')->where('fecha', $fechaISO)->orderBy('id')
            ->get(['id', 'nombre', 'cantidad'])->map(function ($p) {
                return ['id' => $p->id, 'nombre' => $p->nombre, 'cantidad' => (int) $p->cantidad];
            })->values()->all();
        $sugeridos = DB::table('postres_dia')->where('fecha', '>=', Carbon::parse($fechaISO)->subDays(90)->toDateString())
            ->select('nombre')->groupBy('nombre')->orderByRaw('MAX(fecha) DESC')->limit(30)->pluck('nombre')->all();
        return ['postres' => $postres, 'sugeridos' => $sugeridos, 'puede' => self::puede()];
    }

    public function store(Request $request)
    {
        abort_unless(self::puede(), 403, 'Solo cocina puede ingresar postres.');
        $data = $request->validate([
            'fecha'    => 'required|date_format:d-m-Y',
            'nombre'   => 'required|string|max:120',
            'cantidad' => 'required|integer|min:0|max:999',
        ], [
            'nombre.required' => 'Escribe el nombre del postre.',
            'cantidad.required' => 'Indica la cantidad.',
        ]);
        $fecha = Carbon::createFromFormat('d-m-Y', $data['fecha'])->toDateString();
        $nombre = trim(preg_replace('/\s+/', ' ', $data['nombre']));

        // Mismo postre el mismo día: se suma a la cantidad que ya estaba
        $existe = DB::table('postres_dia')->where('fecha', $fecha)->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
        if ($existe) {
            DB::table('postres_dia')->where('id', $existe->id)->update([
                'cantidad' => (int) $existe->cantidad + (int) $data['cantidad'], 'id_user' => Auth::id(), 'updated_at' => now(),
            ]);
        } else {
            DB::table('postres_dia')->insert([
                'fecha' => $fecha, 'nombre' => $nombre, 'cantidad' => (int) $data['cantidad'],
                'id_user' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return response()->json(self::delDia($fecha));
    }

    public function update(Request $request, $id)
    {
        abort_unless(self::puede(), 403, 'Solo cocina puede editar postres.');
        $data = $request->validate(['cantidad' => 'required|integer|min:0|max:999']);
        $p = DB::table('postres_dia')->where('id', $id)->first();
        abort_unless($p, 404);
        DB::table('postres_dia')->where('id', $id)->update(['cantidad' => (int) $data['cantidad'], 'id_user' => Auth::id(), 'updated_at' => now()]);
        return response()->json(self::delDia($p->fecha));
    }

    public function destroy($id)
    {
        abort_unless(self::puede(), 403, 'Solo cocina puede quitar postres.');
        $p = DB::table('postres_dia')->where('id', $id)->first();
        abort_unless($p, 404);
        DB::table('postres_dia')->where('id', $id)->delete();
        return response()->json(self::delDia($p->fecha));
    }
}
