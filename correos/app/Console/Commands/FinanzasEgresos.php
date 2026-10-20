<?php

namespace App\Console\Commands;

use App\Finanzas\Egresos\Categorizador;
use App\Finanzas\Egresos\Deudas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan finanzas:egresos categorizar [--desde=2026-01-01] [--aplicar]
 *     Pasa los egresos a las categorías nuevas según el proveedor. Sin --aplicar solo muestra el resultado.
 *     Con --aplicar guarda un respaldo (storage/app) con la categoría anterior de cada egreso.
 * php artisan finanzas:egresos deudas-iniciales   Carga las deudas conocidas (si no están).
 * php artisan finanzas:egresos cuentas            Las cuentas que llegaron al correo pasan a "Por pagar".
 */
class FinanzasEgresos extends Command
{
    protected $signature = 'finanzas:egresos {accion : categorizar | deudas-iniciales | cuentas} {--desde=2026-01-01} {--aplicar}';
    protected $description = 'Módulo de egresos: categorías nuevas, deudas y cuentas por pagar';

    public function handle(Categorizador $cat, Deudas $deudas)
    {
        switch ($this->argument('accion')) {
            case 'deudas-iniciales':
                $this->info('Deudas cargadas: ' . $deudas->cargarIniciales());
                return 0;
            case 'cuentas':
                $n = 0;
                foreach (DB::table('fin_correos')->where('tipo', 'cuenta')->whereNotIn('estado', ['ignorado', 'sospechoso'])->get() as $c) {
                    $n += $deudas->desdeCuenta($c) ? 1 : 0;
                }
                $this->info("Cuentas en Por pagar: {$n}");
                return 0;
            case 'categorizar':
                return $this->categorizar($cat);
        }
        $this->error('Acción desconocida');
        return 1;
    }

    private function categorizar(Categorizador $cat): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $subs = $cat->subcategorias();
        $q = DB::table('egresos as e')->leftJoin('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->where('e.fecha_egreso', '>=', $this->option('desde'))
            ->select('e.id', 'e.categoria_id', 'e.subcategoria_id', 'e.proveedor_id', 'e.total',
                     Schema::hasColumn('egresos', 'descripcion') ? 'e.descripcion' : DB::raw('NULL as descripcion'), 'p.nombre as proveedor_nombre');
        $resumen = [];
        $sin = [];
        $respaldo = [];
        $cambios = 0;
        foreach ($q->get() as $e) {
            $r = $cat->paraEgreso($e);
            if (!$r) {
                $k = $e->proveedor_nombre ?: '(sin proveedor)';
                $sin[$k] = ($sin[$k] ?? ['n' => 0, 't' => 0]);
                $sin[$k]['n']++;
                $sin[$k]['t'] += (int) $e->total;
                continue;
            }
            $et = $subs[$r['subcategoria_id']]['cat'] . ' › ' . $subs[$r['subcategoria_id']]['sub'];
            $resumen[$et] = ($resumen[$et] ?? ['n' => 0, 't' => 0]);
            $resumen[$et]['n']++;
            $resumen[$et]['t'] += (int) $e->total;
            if ((int) $e->subcategoria_id !== (int) $r['subcategoria_id'] || (int) $e->categoria_id !== (int) $r['categoria_id']) {
                $cambios++;
                if ($aplicar) {
                    $respaldo[$e->id] = [$e->categoria_id, $e->subcategoria_id];
                    DB::table('egresos')->where('id', $e->id)->update(['categoria_id' => $r['categoria_id'], 'subcategoria_id' => $r['subcategoria_id']]);
                    $cat->aprender($e->proveedor_id ? (int) $e->proveedor_id : null, (int) $r['subcategoria_id']);
                }
            }
        }
        ksort($resumen);
        $this->line('Con categoría nueva:');
        foreach ($resumen as $k => $v) {
            $this->line(sprintf('  %-48s %4d  $%s', $k, $v['n'], number_format($v['t'], 0, ',', '.')));
        }
        uasort($sin, function ($a, $b) {
            return $b['t'] <=> $a['t'];
        });
        $this->line('Sin regla (' . count($sin) . ' proveedores, se clasifican en la bandeja o se agrega la palabra):');
        foreach (array_slice($sin, 0, 25, true) as $k => $v) {
            $this->line(sprintf('  %-48s %4d  $%s', mb_substr($k, 0, 48), $v['n'], number_format($v['t'], 0, ',', '.')));
        }
        if ($aplicar && $respaldo) {
            $f = 'egresos_categorias_respaldo_' . date('Ymd_His') . '.json';
            file_put_contents(storage_path('app/' . $f), json_encode($respaldo));
            $this->info("Aplicado a {$cambios} egresos. Respaldo: storage/app/{$f}");
        } else {
            $this->info("Cambiarían {$cambios} egresos. Para guardar: --aplicar");
        }
        return 0;
    }
}
