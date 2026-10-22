<?php

namespace App\Http\Controllers;

use App\PoroPoro;
use Illuminate\Http\Request;

// Crear, editar y eliminar productos Poro Poro desde la misma vista de ventas (respuestas JSON).
class PoroProductosController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $u = $request->user();
            if (!$u || !$u->has_role(config('app.admin_role'))) {
                return response()->json(['mensaje' => 'No tienes permiso para editar productos.'], 403);
            }
            return $next($request);
        });
    }

    private function datos(Request $request)
    {
        return $request->validate([
            'nombre' => 'required|string|max:255',
            'valor' => 'required|integer|min:0',
            'descripcion' => 'nullable|string|max:1500',
        ], [
            'nombre.required' => 'Falta el nombre.',
            'valor.required' => 'Falta el valor.',
            'valor.integer' => 'El valor debe ser un número sin puntos.',
            'valor.min' => 'El valor no puede ser negativo.',
        ]);
    }

    private function json(PoroPoro $p)
    {
        return ['id' => $p->id, 'nombre' => $p->nombre, 'valor' => (int) $p->valor, 'descripcion' => $p->descripcion,
                'ventas' => $p->detalleVentas()->count()];
    }

    public function store(Request $request)
    {
        $p = PoroPoro::create($this->datos($request));
        return response()->json(['mensaje' => 'Producto creado', 'producto' => $this->json($p)]);
    }

    public function update(Request $request, $id)
    {
        $p = PoroPoro::findOrFail($id);
        $p->update($this->datos($request));
        return response()->json(['mensaje' => 'Producto actualizado', 'producto' => $this->json($p)]);
    }

    public function destroy($id)
    {
        $p = PoroPoro::findOrFail($id);
        $n = $p->detalleVentas()->count();
        if ($n > 0) {
            return response()->json(['mensaje' => "No se puede eliminar: tiene $n ventas registradas. Cámbiale el nombre o el valor en su lugar."], 422);
        }
        $p->delete();
        return response()->json(['mensaje' => 'Producto eliminado']);
    }
}
