<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada F29 (IVA a pagar + retención de honorarios + PPM, igual que la vista Impuestos) queda en Por pagar
 * como Impuesto, con vencimiento el día 20 del mes siguiente. Mientras no tenga abonos, el monto se
 * actualiza con la última sincronización del SII. Los abonos del banco o a mano la van pagando.
 *
 *   php artisan finanzas:f29-deudas                      simulación (muestra cada mes)
 *   php artisan finanzas:f29-deudas --aplicar             crea/actualiza desde --desde (por defecto config finanzas.f29_desde)
 *   php artisan finanzas:f29-deudas --pagados=2026-01,2026-02 --aplicar   marca esos meses como ya pagados
 */
class FinanzasF29Deudas extends Command
{
    protected $signature = 'finanzas:f29-deudas {--desde=} {--pagados=} {--solo= : solo estos meses, ej. 2026-01,2026-06} {--aplicar}';
    protected $description = 'Lleva cada F29 (IVA + retenciones + PPM) a Por pagar';

    const TASA_PPM = 0.00125;

    public function handle()
    {
        if (!Schema::hasTable('fin_deudas') || !Schema::hasTable('sii_resumen_mensual')) {
            $this->error('Falta fin_deudas o sii_resumen_mensual');
            return 1;
        }
        $desde = str_replace('-', '', $this->option('desde') ?: config('finanzas.f29_desde', '2026-08'));
        $pagados = array_filter(array_map(function ($p) { return str_replace('-', '', trim($p)); }, explode(',', (string) $this->option('pagados'))));
        $aplicar = (bool) $this->option('aplicar');
        $solo = array_filter(array_map(function ($p) { return str_replace('-', '', trim($p)); }, explode(',', (string) $this->option('solo'))));
        $f29 = app(\App\Services\F29Service::class);
        $hasta = now()->subMonthNoOverflow()->format('Ym'); // el mes en curso aún no se declara
        $cat = DB::table('categorias_compras')->where('nombre', 'Impuestos y obligaciones')->value('id');
        $sub = $cat ? DB::table('subcategorias_compras')->where('categoria_id', $cat)->where('nombre', 'IVA y F29')->value('id') : null;
        $svc = app(\App\Finanzas\Egresos\Deudas::class);

        $filas = DB::table('sii_resumen_mensual')->where('periodo', '<=', $hasta)->orderBy('periodo')->get();
        foreach ($filas as $r) {
            $p = $r->periodo;
            if ($solo && !in_array($p, $solo, true)) {
                continue;
            }
            $mes = Carbon::createFromFormat('Ym d', $p . ' 01');
            $nombreMes = $mes->copy()->locale('es')->isoFormat('MMMM YYYY');
            $iva = $f29->ivaPeriodo($p);
            $ivaPagar = $iva ? (int) $iva['iva_a_pagar'] : max(0, (int) $r->iva_diferencia);
            $ret = (int) $r->honorarios_retencion;
            $ppm = (int) round(((int) $r->ventas_neto + (int) $r->ventas_exento) * self::TASA_PPM);
            $total = $ivaPagar + $ret + $ppm;
            $vence = $mes->copy()->addMonthNoOverflow()->day(20)->toDateString();
            $nombre = 'F29 ' . $nombreMes;
            $d = DB::table('fin_deudas')->where('tipo', 'impuesto')
                ->where(function ($q) use ($nombre, $nombreMes) {
                    $q->where('nombre', $nombre)->orWhere('nombre', 'like', '%' . $nombreMes . '%');
                })->first();
            $estado = $d ? ($d->estado === 'pagada' ? 'pagado' : 'en Por pagar, saldo $' . number_format($svc->saldo($d), 0, ',', '.')) : 'no está';
            $linea = sprintf('%-20s IVA %10s + ret. %8s + PPM %7s = %11s · vence %s · %s', $nombre,
                number_format($ivaPagar, 0, ',', '.'), number_format($ret, 0, ',', '.'), number_format($ppm, 0, ',', '.'),
                '$' . number_format($total, 0, ',', '.'), date('d-m-Y', strtotime($vence)), $estado);

            if ($p < $desde && !in_array($p, $pagados, true) && !in_array($p, $solo, true)) {
                $this->line($linea . ' · (antes de ' . substr($desde, 4) . '-' . substr($desde, 0, 4) . ': no se toca)');
                continue;
            }
            if (!$aplicar) {
                $this->line($linea . (in_array($p, $pagados, true) ? ' → se marcaría pagado' : ($d ? '' : ' → se agregaría')));
                continue;
            }
            if (!$d) {
                $id = DB::table('fin_deudas')->insertGetId(['tipo' => 'impuesto', 'nombre' => $nombre, 'acreedor' => 'Tesorería · F29', 'patron' => 'f29',
                    'ambito' => 'botacura', 'categoria_id' => $cat, 'subcategoria_id' => $sub, 'monto_total' => $total, 'vencimiento' => $vence,
                    'estado' => 'pendiente', 'nota' => 'Estimado con el RCV del SII: IVA ' . number_format($ivaPagar, 0, ',', '.') . ' + retención ' . number_format($ret, 0, ',', '.') . ' + PPM ' . number_format($ppm, 0, ',', '.'),
                    'created_at' => now(), 'updated_at' => now()]);
                $d = DB::table('fin_deudas')->find($id);
                $linea .= ' → agregado';
            } elseif ($d->estado === 'pendiente' && !$svc->pagado((int) $d->id) && (int) $d->monto_total !== $total && strpos($d->nombre, 'F29 ') === 0) {
                DB::table('fin_deudas')->where('id', $d->id)->update(['monto_total' => $total, 'vencimiento' => $vence, 'updated_at' => now()]);
                $linea .= ' → monto actualizado';
            }
            if (in_array($p, $pagados, true) && $svc->saldo($d) > 0) {
                $svc->registrarPago($d, $svc->saldo($d), $vence, 'ajuste', null, 'Ya estaba pagado');
                $linea .= ' → marcado pagado';
            }
            $this->line($linea);
        }
        return 0;
    }
}
