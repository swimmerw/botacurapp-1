<?php

namespace App\Finanzas;

use App\HonorarioBte;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semanas de remuneración de un funcionario, con el mismo total que muestra /sueldos
 * (SueldoController::index): neto (o neto de la BTE si boletea) + propinas.
 * /sueldos agrupa por mes, así que una semana que cruza dos meses aparece en dos partes:
 * se entregan las dos partes y también la semana completa.
 *
 * Sirve para marcar "Pagado" una semana cuando llega el aviso del banco de la transferencia.
 */
class SueldoSemanas
{
    /** @return array lista de ['inicio','fin','total','parte' (null|'mes m'),'pagado'] de las últimas $semanas semanas. */
    public function pendientes(int $userId, string $hasta, int $semanas = 6): array
    {
        $user = User::find($userId);
        if (!$user || !Schema::hasTable('sueldos')) {
            return [];
        }
        $fin = Carbon::parse($hasta)->endOfWeek(Carbon::SUNDAY);
        $ini = $fin->copy()->subWeeks($semanas)->addDay()->startOfWeek(Carbon::MONDAY);

        $dias = DB::table('sueldos')->where('id_user', $userId)
            ->whereBetween('dia_trabajado', [$ini->toDateString(), $fin->toDateString()])
            ->orderBy('dia_trabajado')->get();
        if ($dias->isEmpty()) {
            return [];
        }

        $roles = $user->list_roles();
        $esMaso = stripos((string) $roles, 'Masoterapeuta') !== false;
        $btes = ($user->boletea && $user->rut && Schema::hasTable('honorarios_bte'))
            ? HonorarioBte::where('rut_emisor', $user->rut)->whereBetween('fecha_emision', [$ini->toDateString(), $fin->toDateString()])->get()
            : collect();

        // Agrupar por semana y por mes (como /sueldos)
        $grupos = [];
        foreach ($dias as $d) {
            $f = Carbon::parse($d->dia_trabajado);
            $wi = $f->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $wf = $f->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
            $mes = $f->format('Y-m');
            foreach (["$wi|$wf|$mes", "$wi|$wf|todo"] as $k) {
                if (!isset($grupos[$k])) {
                    $grupos[$k] = ['inicio' => $wi, 'fin' => $wf, 'parte' => substr($k, -4) === 'todo' ? null : $mes,
                                   'sueldos' => 0, 'propinas' => 0];
                }
                $grupos[$k]['sueldos'] += $esMaso ? (int) $d->total_pagar : (int) $d->valor_dia;
                $grupos[$k]['propinas'] += $esMaso ? 0 : ((int) $d->sub_sueldo - (int) $d->valor_dia);
            }
        }

        $pagadas = DB::table('sueldos_pagados')->where('user_id', $userId)
            ->where('semana_inicio', '>=', $ini->toDateString())->get();

        $out = [];
        foreach ($grupos as $k => $g) {
            $partido = substr($g['inicio'], 0, 7) !== substr($g['fin'], 0, 7);
            if (!$partido && $g['parte'] !== null) {
                continue; // semana dentro de un solo mes: basta la fila "todo"
            }
            $bte = $btes->first(function ($h) use ($g) {
                return $h->fecha_emision && $h->fecha_emision->between(Carbon::parse($g['inicio'])->startOfDay(), Carbon::parse($g['fin'])->endOfDay());
            });
            $bteNeto = $bte ? ($bte->monto_pagado ?: ($bte->monto_bruto - $bte->monto_retenido)) : 0;
            $base = ($user->boletea && $bte && $bte->monto_bruto > 0) ? $bteNeto : $g['sueldos'];
            $pagada = $pagadas->first(function ($p) use ($g) {
                return $p->semana_inicio == $g['inicio'] && $p->semana_fin == $g['fin'];
            });
            $out[] = $g + ['total' => $base + $g['propinas'], 'pagado' => (bool) $pagada,
                           'etiqueta' => 'Semana ' . date('d-m', strtotime($g['inicio'])) . ' al ' . date('d-m', strtotime($g['fin']))
                               . ($g['parte'] ? ' (parte de ' . self::mes($g['parte']) . ')' : '')];
        }
        usort($out, function ($a, $b) {
            return strcmp($b['inicio'], $a['inicio']);
        });
        return $out;
    }

    /** Única semana sin pagar cuyo total es exactamente el monto transferido (null si no hay o hay varias). */
    public function semanaPorMonto(int $userId, int $monto, string $fechaPago): ?array
    {
        $hits = array_values(array_filter($this->pendientes($userId, $fechaPago), function ($s) use ($monto, $fechaPago) {
            return !$s['pagado'] && (int) $s['total'] === $monto && $s['inicio'] <= $fechaPago;
        }));
        return count($hits) === 1 ? $hits[0] : null;
    }

    /** Marca la semana como pagada en Remuneraciones (igual que el botón "Pagar seleccionados"). */
    public function marcarPagada(int $userId, array $semana, string $fechaPago): int
    {
        $existe = DB::table('sueldos_pagados')->where('user_id', $userId)
            ->where('semana_inicio', $semana['inicio'])->where('semana_fin', $semana['fin'])->value('id');
        if ($existe) {
            return (int) $existe;
        }
        $fila = ['user_id' => $userId, 'semana_inicio' => $semana['inicio'], 'semana_fin' => $semana['fin'],
                 'fecha_pago' => $fechaPago, 'monto' => (int) $semana['total'], 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('sueldos_pagados', 'bono')) {
            $fila['bono'] = 0;
        }
        if (Schema::hasColumn('sueldos_pagados', 'confirmado')) {
            $fila['confirmado'] = true;
            $fila['confirmado_at'] = now();
        }
        return (int) DB::table('sueldos_pagados')->insertGetId($fila);
    }

    private static function mes(string $ym): string
    {
        $m = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio',
              '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
        return $m[substr($ym, 5, 2)] ?? $ym;
    }
}
