<?php

namespace App\Finanzas\Egresos;

use App\Finanzas\Correo\ConciliadorSalidas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Todo lo que hay por pagar: cuentas que llegan al correo (CGE, Movistar...), deudas (Susana, préstamos,
 * tarjeta) y créditos en cuotas. Cada abono (aviso del banco o a mano) baja el saldo; en cero queda pagada.
 */
class Deudas
{
    public static function hay(): bool
    {
        return Schema::hasTable('fin_deudas');
    }

    public function pagado(int $deudaId): int
    {
        return (int) DB::table('fin_deuda_pagos')->where('deuda_id', $deudaId)->sum('monto');
    }

    public function saldo($d): int
    {
        return max(0, (int) $d->monto_total - $this->pagado((int) $d->id));
    }

    /** Pendientes con su saldo y su último abono, ordenadas por vencimiento. */
    public function pendientes(?string $ambito = null)
    {
        $q = DB::table('fin_deudas')->where('estado', 'pendiente');
        if ($ambito) {
            $q->where('ambito', $ambito);
        }
        return $q->orderByRaw('vencimiento IS NULL')->orderBy('vencimiento')->orderBy('id')->get()->map(function ($d) {
            $d->pagado = $this->pagado((int) $d->id);
            $d->saldo = max(0, (int) $d->monto_total - $d->pagado);
            $d->ultimo = DB::table('fin_deuda_pagos')->where('deuda_id', $d->id)->orderByDesc('fecha')->orderByDesc('id')->first();
            $d->vencida = $d->vencimiento && $d->vencimiento < date('Y-m-d');
            return $d;
        });
    }

    /** Una cuenta que llegó al correo queda como deuda (una sola vez). */
    public function desdeCuenta($c): ?int
    {
        if (!self::hay() || $c->tipo !== 'cuenta' || !$c->monto) {
            return null;
        }
        $ya = DB::table('fin_deudas')->where('correo_id', $c->id)->value('id');
        if ($ya) {
            return (int) $ya;
        }
        // La misma boleta repetida en otro correo
        if ($c->operacion) {
            $dup = DB::table('fin_deudas')->where('tipo', 'cuenta')->where('acreedor', $c->proveedor)
                ->where('monto_total', (int) $c->monto)->where('nombre', 'like', '%N° ' . $c->operacion . '%')->value('id');
            if ($dup) {
                return (int) $dup;
            }
        }
        $cat = app(Categorizador::class)->paraNombre($c->proveedor ?: $c->nombre);
        return (int) DB::table('fin_deudas')->insertGetId([
            'tipo' => 'cuenta',
            'nombre' => mb_substr(trim(($c->proveedor ?: 'Cuenta') . ($c->operacion ? ' · N° ' . $c->operacion : '')), 0, 150),
            'acreedor' => $c->proveedor, 'patron' => $c->proveedor ? mb_strtolower($c->proveedor) : null,
            'ambito' => 'botacura',
            'categoria_id' => $cat['categoria_id'] ?? null, 'subcategoria_id' => $cat['subcategoria_id'] ?? null,
            'monto_total' => (int) $c->monto, 'vencimiento' => $c->vencimiento, 'correo_id' => $c->id,
            'estado' => 'pendiente', 'nota' => mb_substr((string) $c->asunto, 0, 191),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * ¿Esta salida del banco es un abono a una deuda pendiente? Coincide el RUT, el proveedor
     * o una palabra del patrón, y el monto no supera el saldo.
     */
    public function paraSalida($c)
    {
        if (!self::hay() || !$c->monto) {
            return null;
        }
        $texto = ' ' . Categorizador::normal(trim($c->nombre . ' ' . $c->proveedor . ' ' . $c->comentario)) . ' ';
        $cands = [];
        foreach (DB::table('fin_deudas')->where('estado', 'pendiente')->get() as $d) {
            $saldo = $this->saldo($d);
            if ($saldo <= 0 || (int) $c->monto > $saldo) {
                continue;
            }
            // Solo pagos posteriores a cuando se anotó la deuda (los anteriores ya están descontados del monto)
            if ($d->tipo === 'cuenta' && $d->correo_id) {
                $desde = date('Y-m-d', strtotime((string) DB::table('fin_correos')->where('id', $d->correo_id)->value('recibido_at') . ' -3 days'));
                if (($c->fecha ?: substr((string) $c->recibido_at, 0, 10)) < $desde) {
                    continue;
                }
            } elseif ($d->created_at && (string) $c->recibido_at <= (string) $d->created_at) {
                continue;
            }
            $ok = $c->rut && $d->rut && ConciliadorSalidas::rut($c->rut) === ConciliadorSalidas::rut($d->rut);
            if (!$ok && $d->patron) {
                foreach (array_filter(array_map('trim', explode(',', $d->patron))) as $p) {
                    $p = Categorizador::normal($p);
                    if ($p !== '' && preg_match('/(^|\s)' . preg_quote($p, '/') . '/', $texto)) {
                        $ok = true;
                        break;
                    }
                }
            }
            if (!$ok) {
                continue;
            }
            // Ya usado este aviso
            if (DB::table('fin_deuda_pagos')->where('correo_id', $c->id)->exists()) {
                return null;
            }
            $exacto = ((int) $c->monto === $saldo) || ($d->cuota && (int) $c->monto === (int) $d->cuota);
            $cands[] = [$exacto ? 0 : 1, $d->vencimiento ?: '9999', $d];
        }
        if (!$cands) {
            return null;
        }
        usort($cands, function ($a, $b) {
            return [$a[0], $a[1]] <=> [$b[0], $b[1]];
        });
        return $cands[0][2];
    }

    public function registrarPago($d, int $monto, string $fecha, string $medio, ?int $correoId = null, ?string $nota = null): int
    {
        $id = (int) DB::table('fin_deuda_pagos')->insertGetId([
            'deuda_id' => $d->id, 'monto' => $monto, 'fecha' => $fecha, 'medio' => $medio, 'correo_id' => $correoId,
            'user_id' => auth()->id(), 'nota' => $nota ? mb_substr($nota, 0, 191) : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->recalcular((int) $d->id);
        return $id;
    }

    /** Estado según el saldo; si la deuda es una cuenta del correo, también marca ese correo. */
    public function recalcular(int $deudaId): void
    {
        $d = DB::table('fin_deudas')->where('id', $deudaId)->first();
        if (!$d || $d->estado === 'anulada') {
            return;
        }
        $saldo = $this->saldo($d);
        $estado = $saldo <= 0 ? 'pagada' : 'pendiente';
        DB::table('fin_deudas')->where('id', $deudaId)->update(['estado' => $estado, 'updated_at' => now()]);
        if ($d->correo_id) {
            DB::table('fin_correos')->where('id', $d->correo_id)->update([
                'estado' => $saldo <= 0 ? 'pagado' : 'revisar',
                'nota' => $saldo <= 0 ? 'Pagada' : 'Saldo $' . number_format($saldo, 0, ',', '.'),
                'updated_at' => now(),
            ]);
        }
    }

    /** Deudas conocidas al 19/10/2026 (montos estimados, editables). Solo si no existen. */
    public function cargarIniciales(): int
    {
        $base = [
            ['tipo' => 'deuda', 'nombre' => 'Susana Olivares · verduras', 'acreedor' => 'Susana Olivares', 'rut' => '9.355.184-2', 'patron' => 'olivares',
             'monto_total' => 440700, 'nota' => 'Saldo al 19/10 después de los pagos del 30-09 y 19-10'],
            ['tipo' => 'deuda', 'nombre' => 'Visa Dorada Banco de Chile', 'acreedor' => 'Banco de Chile', 'patron' => 'visa, tarjeta de credito',
             'monto_total' => 2193244, 'ambito' => 'personal', 'nota' => 'Facturado sep-2026; interés 3,42 % mensual. Estimado'],
            ['tipo' => 'deuda', 'nombre' => 'Línea de crédito', 'acreedor' => 'Banco de Chile', 'patron' => 'linea de credito',
             'monto_total' => 500000, 'ambito' => 'personal', 'nota' => 'Estimado'],
            ['tipo' => 'deuda', 'nombre' => 'Préstamo Luis Silva Toro', 'acreedor' => 'Luis Silva Toro', 'patron' => 'luis silva',
             'monto_total' => 5000000, 'nota' => 'Estimado'],
            ['tipo' => 'credito', 'nombre' => 'Hipotecario Santander', 'acreedor' => 'Banco Santander', 'patron' => 'hipotecario',
             'monto_total' => 23 * 475716, 'cuota' => 475716, 'cuotas_total' => 138, 'nota' => 'Cuota 116 de 138 pagada; lo paga Michelle'],
            ['tipo' => 'deuda', 'nombre' => 'Gas · diferencia facturado vs pagado (por confirmar)', 'acreedor' => 'Luis Patricio Velásquez / Pierattini', 'patron' => 'luis patricio, pierattini',
             'monto_total' => 2720000, 'nota' => 'Por confirmar con el estado de cuenta del proveedor'],
        ];
        $n = 0;
        $cat = app(Categorizador::class);
        foreach ($base as $d) {
            if (DB::table('fin_deudas')->where('nombre', $d['nombre'])->exists()) {
                continue;
            }
            $c = $cat->paraNombre($d['acreedor'] . ' ' . $d['nombre']);
            DB::table('fin_deudas')->insert($d + ['ambito' => 'botacura', 'estado' => 'pendiente',
                'categoria_id' => $c['categoria_id'] ?? null, 'subcategoria_id' => $c['subcategoria_id'] ?? null,
                'created_at' => now(), 'updated_at' => now()]);
            $n++;
        }
        return $n;
    }
}
