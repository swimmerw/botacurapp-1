<?php

namespace App\Finanzas\Correo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cruza cada abono recibido (aviso del banco) con lo que espera BotacurApp:
 *  1. comprobantes del bot en revisión (antes de crear la reserva)
 *  2. abono o diferencia de una venta
 *  3. abonos extra
 * Calce seguro (monto + nombre o N° de operación) => "calzado".
 * Solo coincide el monto con un único candidato => "revisar" con la sugerencia.
 */
class ConciliadorAbonos
{
    public function conciliar($correo): array
    {
        if (!$correo->monto) {
            return ['estado' => 'revisar', 'nota' => 'Sin monto legible'];
        }
        $fecha = $correo->fecha ?: substr((string) $correo->recibido_at, 0, 10);
        $cands = array_merge(
            $this->comprobantes($correo, $fecha),
            $this->ventas($correo, $fecha),
            $this->abonosExtra($correo, $fecha)
        );
        if (!$cands) {
            return ['estado' => 'revisar', 'nota' => 'Sin reserva o venta por este monto'];
        }
        usort($cands, function ($a, $b) {
            return $b['puntos'] <=> $a['puntos'];
        });
        $mejor = $cands[0];
        $empate = isset($cands[1]) && $cands[1]['puntos'] === $mejor['puntos'];

        $res = ['venta_id' => $mejor['venta_id'] ?? null, 'campo' => $mejor['campo'] ?? null,
                'abono_extra_id' => $mejor['abono_extra_id'] ?? null, 'comprobante_id' => $mejor['comprobante_id'] ?? null];
        if ($mejor['puntos'] >= 2 && !$empate) {
            return $res + ['estado' => 'calzado', 'nota' => $mejor['motivo']];
        }
        if (count($cands) === 1) {
            return $res + ['estado' => 'revisar', 'nota' => 'Solo coincide el monto: ' . $mejor['motivo']];
        }
        return ['estado' => 'revisar', 'nota' => count($cands) . ' candidatos con el mismo monto'];
    }

    private function comprobantes($c, string $fecha): array
    {
        if (!Schema::hasTable('comprobantes_revision')) {
            return [];
        }
        $out = [];
        $filas = DB::table('comprobantes_revision as cr')
            ->leftJoin('reserva_holds as h', 'h.id', '=', 'cr.hold_id')
            ->where('cr.estado', 'pendiente')
            ->where('cr.created_at', '>=', date('Y-m-d', strtotime($fecha . ' -5 days')))
            ->select('cr.id', 'cr.datos_json', 'h.nombre', 'h.abono_50')
            ->get();
        foreach ($filas as $f) {
            $d = json_decode($f->datos_json ?? '{}', true) ?: [];
            $montoComp = (int) ($d['monto'] ?? 0);
            if ($montoComp !== (int) $c->monto && (int) $f->abono_50 !== (int) $c->monto) {
                continue;
            }
            list($p, $m) = $this->puntaje($c, [$f->nombre, $d['nombre_origen'] ?? null], $d['numero_operacion'] ?? null);
            $out[] = ['comprobante_id' => $f->id, 'puntos' => $p + 1, 'motivo' => 'comprobante del bot en revisión #' . $f->id . $m];
        }
        return $out;
    }

    private function ventas($c, string $fecha): array
    {
        $out = [];
        $q = DB::table('ventas as v')
            ->join('reservas as r', 'r.id', '=', 'v.id_reserva')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'r.cliente_id')
            ->where(function ($w) use ($c) {
                $w->where('v.abono_programa', $c->monto)->orWhere('v.diferencia_programa', $c->monto);
            })
            ->whereBetween('r.fecha_visita', [date('Y-m-d', strtotime($fecha . ' -3 days')), date('Y-m-d', strtotime($fecha . ' +90 days'))]);
        $cols = ['v.id', 'v.abono_programa', 'v.diferencia_programa', 'r.fecha_visita', 'cl.nombre_cliente'];
        foreach (['comprobante_nombre_origen', 'comprobante_numero_operacion'] as $col) {
            if (Schema::hasColumn('ventas', $col)) {
                $cols[] = 'v.' . $col;
            }
        }
        foreach ($q->select($cols)->get() as $v) {
            $esAbono = (int) $v->abono_programa === (int) $c->monto;
            $esDif = (int) $v->diferencia_programa === (int) $c->monto;
            $campo = $esAbono ? 'abono' : 'diferencia';
            if ($esAbono && $esDif) {
                // Mismo monto: si el abono ya llegó o el pago es a días de la visita, es la diferencia
                $abonoListo = DB::table('fin_correos')->where('venta_id', $v->id)->where('campo', 'abono')
                    ->where('estado', 'calzado')->where('id', '<>', $c->id)->exists();
                if ($abonoListo || abs(strtotime($v->fecha_visita) - strtotime($fecha)) <= 2 * 86400) {
                    $campo = 'diferencia';
                }
            }
            // La diferencia se paga cerca del día de la visita
            if ($campo === 'diferencia' && abs(strtotime($v->fecha_visita) - strtotime($fecha)) > 4 * 86400) {
                continue;
            }
            $ya = DB::table('fin_correos')->where('venta_id', $v->id)->where('campo', $campo)
                ->where('estado', 'calzado')->where('id', '<>', $c->id)->exists();
            if ($ya) {
                continue;
            }
            list($p, $m) = $this->puntaje($c, [$v->nombre_cliente, $v->comprobante_nombre_origen ?? null], $v->comprobante_numero_operacion ?? null);
            $out[] = ['venta_id' => $v->id, 'campo' => $campo, 'puntos' => $p,
                      'motivo' => "venta #{$v->id} ({$campo}, visita {$v->fecha_visita})" . $m];
        }
        return $out;
    }

    private function abonosExtra($c, string $fecha): array
    {
        if (!Schema::hasTable('abonos_extra')) {
            return [];
        }
        $out = [];
        $filas = DB::table('abonos_extra as a')
            ->join('ventas as v', 'v.id', '=', 'a.id_venta')
            ->join('reservas as r', 'r.id', '=', 'v.id_reserva')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'r.cliente_id')
            ->where('a.monto', $c->monto)
            ->whereBetween('a.fecha_abono', [date('Y-m-d', strtotime($fecha . ' -3 days')), date('Y-m-d', strtotime($fecha . ' +3 days'))])
            ->select('a.id', 'a.id_venta', 'cl.nombre_cliente')->get();
        foreach ($filas as $a) {
            list($p, $m) = $this->puntaje($c, [$a->nombre_cliente], null);
            $out[] = ['venta_id' => $a->id_venta, 'abono_extra_id' => $a->id, 'campo' => 'extra', 'puntos' => $p + 1,
                      'motivo' => "abono extra #{$a->id}" . $m];
        }
        return $out;
    }

    /** +3 si coincide el N° de operación, +2 si coincide el nombre. */
    private function puntaje($c, array $nombres, ?string $op): array
    {
        $p = 0;
        $m = '';
        $opC = preg_replace('/\D/', '', (string) $c->operacion);
        $opV = preg_replace('/\D/', '', (string) $op);
        if (strlen($opC) >= 5 && strlen($opV) >= 5 && (strpos($opC, $opV) !== false || strpos($opV, $opC) !== false)) {
            $p += 3;
            $m .= ', mismo N° de operación';
        }
        foreach ($nombres as $n) {
            if ($n && self::mismoNombre($c->nombre, $n)) {
                $p += 2;
                $m .= ', mismo nombre';
                break;
            }
        }
        return [$p, $m];
    }

    public static function tokens(?string $s): array
    {
        $s = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $s));
        $t = preg_split('/[^A-Z]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        $stop = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'SPA', 'LTDA', 'LIMITADA', 'SA', 'EIRL', 'SR', 'SRA'];
        return array_values(array_diff(array_filter($t, function ($x) {
            return strlen($x) >= 3;
        }), $stop));
    }

    /** Dos nombres coinciden si comparten al menos 2 palabras (o 1 si uno solo tiene una). */
    public static function mismoNombre(?string $a, ?string $b): bool
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if (!$ta || !$tb) {
            return false;
        }
        $comun = count(array_intersect($ta, $tb));
        return $comun >= 2 || ($comun >= 1 && min(count($ta), count($tb)) === 1);
    }

    /** Guarda el resultado del cruce. No cambia el estado de la venta ni aprueba comprobantes: solo los marca. */
    public function aplicar($correo, array $res): void
    {
        DB::table('fin_correos')->where('id', $correo->id)->update(array_merge(
            array_intersect_key($res, array_flip(['estado', 'nota', 'venta_id', 'campo', 'abono_extra_id', 'comprobante_id'])),
            ['updated_at' => now()]
        ));
    }
}
