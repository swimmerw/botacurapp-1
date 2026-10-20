<?php

namespace App\Finanzas\Egresos;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asigna categoría y subcategoría (árbol de config/egresos.php) a un gasto:
 *  1. la regla aprendida del proveedor (proveedores.subcategoria_id, si es del árbol nuevo)
 *  2. las palabras del árbol en el nombre del proveedor o de quien recibe el pago.
 */
class Categorizador
{
    private $subs = null;     // sub_id => ['cat_id','cat','sub']
    private $palabras = null; // [[palabra, sub_id], ...] de la más larga a la más corta

    private function cargar(): void
    {
        if ($this->subs !== null) {
            return;
        }
        $this->subs = [];
        $this->palabras = [];
        if (!Schema::hasTable('subcategorias_compras')) {
            return;
        }
        $cats = DB::table('categorias_compras')->pluck('id', 'nombre');
        foreach ((array) config('egresos.arbol', []) as $cat => $subs) {
            if (!isset($cats[$cat])) {
                continue;
            }
            foreach ($subs as $sub => $palabras) {
                $id = DB::table('subcategorias_compras')->where('categoria_id', $cats[$cat])->where('nombre', $sub)->value('id');
                if (!$id) {
                    continue;
                }
                $this->subs[$id] = ['cat_id' => (int) $cats[$cat], 'cat' => $cat, 'sub' => $sub];
                foreach ($palabras as $p) {
                    $this->palabras[] = [self::normal($p), (int) $id];
                }
            }
        }
        usort($this->palabras, function ($a, $b) {
            return strlen($b[0]) <=> strlen($a[0]);
        });
    }

    /** sub_id => datos, solo del árbol nuevo. */
    public function subcategorias(): array
    {
        $this->cargar();
        return $this->subs;
    }

    public function esNueva(?int $subId): bool
    {
        $this->cargar();
        return $subId && isset($this->subs[$subId]);
    }

    /** @return array|null ['categoria_id','subcategoria_id','por'] */
    public function paraNombre(?string $nombre, ?int $proveedorId = null): ?array
    {
        $this->cargar();
        if ($proveedorId && Schema::hasColumn('proveedores', 'subcategoria_id')) {
            $sub = (int) DB::table('proveedores')->where('id', $proveedorId)->value('subcategoria_id');
            if ($this->esNueva($sub)) {
                return ['categoria_id' => $this->subs[$sub]['cat_id'], 'subcategoria_id' => $sub, 'por' => 'proveedor'];
            }
        }
        $n = ' ' . self::normal((string) $nombre) . ' ';
        foreach ($this->palabras as list($p, $sub)) {
            if ($p !== '' && preg_match('/(^|\s)' . preg_quote($p, '/') . '/', $n)) {
                return ['categoria_id' => $this->subs[$sub]['cat_id'], 'subcategoria_id' => $sub, 'por' => 'palabra "' . $p . '"'];
            }
        }
        return null;
    }

    /** Categoría de un egreso ya registrado (por su subcategoría actual, su proveedor o su descripción). */
    public function paraEgreso($e): ?array
    {
        $this->cargar();
        if ($this->esNueva((int) ($e->subcategoria_id ?? 0))) {
            return ['categoria_id' => $this->subs[$e->subcategoria_id]['cat_id'], 'subcategoria_id' => (int) $e->subcategoria_id, 'por' => 'registrado'];
        }
        $nombre = $e->proveedor_nombre ?? null;
        if (!$nombre && !empty($e->proveedor_id)) {
            $nombre = DB::table('proveedores')->where('id', $e->proveedor_id)->value('nombre');
        }
        // La subcategoría antigua es el nombre del proveedor: también sirve como pista
        if (!$nombre && !empty($e->subcategoria_id)) {
            $nombre = DB::table('subcategorias_compras')->where('id', $e->subcategoria_id)->value('nombre');
        }
        return $this->paraNombre(trim($nombre . ' ' . ($e->descripcion ?? '')), $e->proveedor_id ?? null);
    }

    /** Aprende: el proveedor queda con esta subcategoría para las próximas veces. */
    public function aprender(?int $proveedorId, ?int $subId): void
    {
        if ($proveedorId && $this->esNueva($subId) && Schema::hasColumn('proveedores', 'subcategoria_id')) {
            DB::table('proveedores')->where('id', $proveedorId)->update(['subcategoria_id' => $subId, 'updated_at' => now()]);
        }
    }

    public static function normal(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim(preg_replace('/[^a-z0-9:.]+/', ' ', $s));
    }
}
