<?php

namespace App\Finanzas\Correo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cruza cada salida de dinero avisada por el banco con lo registrado en BotacurApp:
 *  - pago desde Botacura a una persona del equipo  -> sueldo pagado (sueldos_pagados)
 *  - pago o compra de Botacura a un proveedor      -> pago de un egreso (pagos_egresos)
 *  - pago desde la cuenta personal de un gasto de Botacura -> "reembolso" (Botacura le debe al socio)
 *  - pago de una cuenta que llegó por correo (CGE, Movistar...) -> la cuenta queda pagada
 *  - retiros de socios y traspasos entre cuentas de Botacura se reconocen solos.
 * Nunca crea ni cambia sueldos ni egresos: solo deja el vínculo y una nota en fin_correos.
 */
class ConciliadorSalidas
{
    const TIPOS = ['pago', 'retiro', 'traspaso', 'compra', 'personal', 'cuenta'];

    public function conciliar($c): array
    {
        if ($c->tipo === 'retiro') {
            return ['estado' => 'calzado', 'vinculo' => 'retiro', 'nota' => 'Retiro de socio'];
        }
        if ($c->tipo === 'traspaso') {
            return ['estado' => 'calzado', 'vinculo' => 'traspaso', 'nota' => 'Traspaso entre cuentas de Botacura'];
        }
        if ($c->tipo === 'cuenta') {
            if (\App\Finanzas\Egresos\Deudas::hay()) {
                // La cuenta queda en "Por pagar"; los abonos del banco la van bajando
                app(\App\Finanzas\Egresos\Deudas::class)->desdeCuenta($c);
                return [];
            }
            return $this->cuentaPagada($c);
        }
        if (!$c->monto) {
            return $c->tipo === 'personal' ? [] : ['estado' => 'revisar', 'nota' => 'Sin monto legible'];
        }

        // ¿Es un abono a algo por pagar (cuenta del correo, deuda, crédito)?
        if (\App\Finanzas\Egresos\Deudas::hay()) {
            $d = app(\App\Finanzas\Egresos\Deudas::class)->paraSalida($c);
            if ($d) {
                return ['estado' => 'calzado', 'vinculo' => 'deuda', 'deuda_id' => $d->id,
                        'categoria_id' => $d->categoria_id, 'subcategoria_id' => $d->subcategoria_id,
                        'nota' => 'Abono a ' . $d->nombre];
            }
        } elseif ($c->proveedor && ($cta = $this->cuentaAbiertaPara($c))) {
            return ['estado' => 'calzado', 'vinculo' => 'cuenta', 'correo_id' => $cta->id,
                    'nota' => "Paga la cuenta {$cta->proveedor} recibida el " . date('d-m', strtotime($cta->recibido_at))];
        }

        $cands = $this->candidatos($c);
        $mejor = $cands[0] ?? null;
        $empate = isset($cands[1]) && $cands[1]['puntos'] === ($mejor['puntos'] ?? -1);

        if ($mejor && $mejor['puntos'] >= 3 && !$empate) {
            $res = ['estado' => 'calzado', 'nota' => $mejor['label'],
                    'vinculo' => $c->tipo === 'personal' ? 'reembolso' : $mejor['clase'],
                    'sueldo_pagado_id' => $mejor['clase'] === 'sueldo' ? $mejor['id'] : null,
                    'pago_egreso_id' => $mejor['clase'] === 'egreso' ? $mejor['id'] : null,
                    'egreso_id' => $mejor['clase'] === 'factura' ? $mejor['id'] : null];
            if ($c->tipo === 'personal') {
                $res['nota'] = 'Gasto de Botacura pagado desde tu cuenta: ' . $mejor['label'];
            }
            return $res;
        }

        if ($c->tipo === 'personal') {
            // Gasto personal sin relación con Botacura: queda como está
            return $mejor && $mejor['puntos'] >= 2
                ? ['estado' => 'revisar', 'nota' => '¿Es de Botacura? ' . $mejor['label']]
                : [];
        }
        // Remuneración: la semana sin pagar de ese funcionario cuyo total es exactamente este monto
        $como = null;
        $persona = $this->persona($c, $como);
        if ($c->tipo === 'pago' && $persona && ($como !== 'nombre' || preg_match('/remuneraci|sueldo/i', (string) $c->comentario))) {
            $sem = app(\App\Finanzas\SueldoSemanas::class)->semanaPorMonto((int) $persona->id, (int) $c->monto, $c->fecha ?: date('Y-m-d'));
            if ($sem) {
                if (($c->seguridad ?? 'seguro') !== 'seguro') {
                    return ['estado' => 'revisar', 'nota' => "Calza con {$persona->name}, {$sem['etiqueta']}, pero el correo no pasó la verificación del banco"];
                }
                return ['estado' => 'calzado', 'vinculo' => 'sueldo', 'pagar_semana' => $sem, 'user_id' => $persona->id,
                        'nota' => "Remuneración de {$persona->name}, {$sem['etiqueta']}: marcada pagada"];
            }
        }
        if ($mejor && $mejor['puntos'] >= 2) {
            return ['estado' => 'revisar', 'nota' => 'Posible: ' . $mejor['label']];
        }
        if ($persona) {
            $iguales = count(array_filter(app(\App\Finanzas\SueldoSemanas::class)->pendientes((int) $persona->id, $c->fecha ?: date('Y-m-d')), function ($w) use ($c) {
                return !$w['pagado'] && (int) $w['total'] === (int) $c->monto;
            }));
            return ['estado' => 'revisar', 'nota' => $iguales > 1
                ? "Pago a {$persona->name}: {$iguales} semanas sin pagar por este monto, elige cuál en la ficha"
                : "Pago a {$persona->name}: ninguna semana sin pagar suma este monto"];
        }
        return ['estado' => 'revisar', 'nota' => $c->tipo === 'compra' ? 'Compra sin egreso registrado' : 'Pago sin registrar en BotacurApp'];
    }

    /** Guarda el resultado solo en correos que no se tocaron a mano. */
    public function aplicar($c, array $res): void
    {
        if (!$res || !in_array($c->estado, ['nuevo', 'revisar'], true)) {
            return;
        }
        if (!empty($res['pagar_semana'])) {
            // Marca la semana "Pagado" en Remuneraciones con la fecha de la transferencia
            $res['sueldo_pagado_id'] = app(\App\Finanzas\SueldoSemanas::class)
                ->marcarPagada((int) $res['user_id'], $res['pagar_semana'], $c->fecha ?: date('Y-m-d'));
        }
        if (!empty($res['deuda_id'])) {
            $d = DB::table('fin_deudas')->where('id', $res['deuda_id'])->first();
            if ($d) {
                app(\App\Finanzas\Egresos\Deudas::class)->registrarPago($d, (int) $c->monto, $c->fecha ?: date('Y-m-d'), 'banco', (int) $c->id);
            }
        }
        // Categoría para el panel de egresos (si no viene de una factura o deuda)
        if (($res['estado'] ?? '') === 'calzado' && empty($res['categoria_id']) && empty($res['egreso_id'])
            && in_array($c->tipo, ['pago', 'compra', 'personal'], true) && Schema::hasColumn('fin_correos', 'categoria_id')) {
            $cat = app(\App\Finanzas\Egresos\Categorizador::class)->paraNombre(trim($c->nombre . ' ' . $c->proveedor));
            if ($cat) {
                $res['categoria_id'] = $cat['categoria_id'];
                $res['subcategoria_id'] = $cat['subcategoria_id'];
            }
            if (($res['vinculo'] ?? '') === 'sueldo') {
                $sub = $this->subId('Personas', 'Sueldos del equipo');
                if ($sub) {
                    $res['categoria_id'] = $sub[0];
                    $res['subcategoria_id'] = $sub[1];
                }
            }
        }
        $cols = ['estado', 'nota', 'vinculo', 'sueldo_pagado_id', 'pago_egreso_id', 'correo_id'];
        if (Schema::hasColumn('fin_correos', 'categoria_id')) {
            $cols = array_merge($cols, ['categoria_id', 'subcategoria_id', 'egreso_id']);
        }
        DB::table('fin_correos')->where('id', $c->id)->update(
            array_intersect_key($res, array_flip($cols)) + ['updated_at' => now()]
        );
        // La cuenta pagada también queda marcada
        if (($res['vinculo'] ?? null) === 'cuenta' && !empty($res['correo_id'])) {
            DB::table('fin_correos')->where('id', $res['correo_id'])->whereIn('estado', ['nuevo', 'revisar'])
                ->update(['estado' => 'pagado', 'correo_id' => $c->id,
                          'nota' => 'Pagada el ' . ($c->fecha ? date('d-m', strtotime($c->fecha)) : '') . ' (aviso del banco)',
                          'updated_at' => now()]);
        }
    }

    /**
     * Sueldos y pagos de egresos que podrían ser esta salida, con puntaje:
     * monto exacto +2, persona/proveedor por RUT o cuenta +2, por nombre +1, fecha cercana +1.
     */
    public function candidatos($c): array
    {
        $fecha = $c->fecha ?: substr((string) $c->recibido_at, 0, 10);
        $out = [];
        $persona = $this->persona($c);

        if (Schema::hasTable('sueldos_pagados')) {
            $q = DB::table('sueldos_pagados as s')->join('users as u', 'u.id', '=', 's.user_id')
                ->whereBetween('s.fecha_pago', [date('Y-m-d', strtotime($fecha . ' -12 days')), date('Y-m-d', strtotime($fecha . ' +4 days'))])
                ->select('s.id', 's.user_id', 's.monto', 's.bono', 's.fecha_pago', 's.semana_inicio', 's.semana_fin', 'u.name');
            if ($persona) {
                $q->where('s.user_id', $persona->id);
            } else {
                $q->where(function ($w) use ($c) {
                    $w->where('s.monto', $c->monto)->orWhere(DB::raw('s.monto + COALESCE(s.bono, 0)'), $c->monto);
                });
            }
            foreach ($q->limit(20)->get() as $s) {
                $total = (int) $s->monto + (int) $s->bono;
                $pts = ($total === (int) $c->monto || (int) $s->monto === (int) $c->monto) ? 2 : 0;
                $pts += ($persona && $persona->id == $s->user_id) ? 2 : (self::mismoNombre($c->nombre, $s->name) ? 1 : 0);
                $pts += abs(strtotime($s->fecha_pago) - strtotime($fecha)) <= 3 * 86400 ? 1 : 0;
                if ($total !== (int) $c->monto && (int) $s->monto !== (int) $c->monto) {
                    $pts = min($pts, 2); // otro monto: nunca se calza solo
                }
                if ($pts >= 2) {
                    $out[] = ['clase' => 'sueldo', 'id' => $s->id, 'puntos' => $pts,
                        'label' => "Sueldo de {$s->name}, semana " . date('d-m', strtotime($s->semana_inicio)) . ' al ' . date('d-m', strtotime($s->semana_fin))
                            . ' ($' . number_format($total, 0, ',', '.') . ')'];
                }
            }
        }

        if (Schema::hasTable('pagos_egresos')) {
            $filas = DB::table('pagos_egresos as pe')->join('egresos as e', 'e.id', '=', 'pe.egreso_id')
                ->leftJoin('proveedores as p', 'p.id', '=', 'e.proveedor_id')
                ->where('pe.monto', $c->monto)
                ->whereBetween('pe.fecha_pago', [date('Y-m-d', strtotime($fecha . ' -10 days')), date('Y-m-d', strtotime($fecha . ' +10 days'))])
                ->select('pe.id', 'pe.monto', 'pe.fecha_pago', 'pe.folio', 'e.id as egreso_id', 'p.nombre as proveedor', 'p.rut')
                ->limit(20)->get();
            foreach ($filas as $e) {
                $pts = 2;
                if ($c->rut && $e->rut && self::rut($c->rut) === self::rut($e->rut)) {
                    $pts += 2;
                } elseif (self::mismoNombre($c->nombre, $e->proveedor, 1)) {
                    $pts += 1;
                }
                $pts += abs(strtotime($e->fecha_pago) - strtotime($fecha)) <= 3 * 86400 ? 1 : 0;
                $out[] = ['clase' => 'egreso', 'id' => $e->id, 'puntos' => $pts,
                    'label' => 'Egreso #' . $e->egreso_id . ' · ' . ($e->proveedor ?: 'sin proveedor') . ($e->folio ? ' · folio ' . $e->folio : '')
                        . ' · pagado ' . date('d-m', strtotime($e->fecha_pago))];
            }
        }

        // Facturas del SII (egresos) que todavía no tienen pago: el cuadre factura ↔ pago
        if (Schema::hasTable('egresos') && Schema::hasColumn('egresos', 'fecha_egreso')) {
            $q = DB::table('egresos as e')->leftJoin('proveedores as p', 'p.id', '=', 'e.proveedor_id')
                ->where('e.total', $c->monto)
                ->whereBetween('e.fecha_egreso', [date('Y-m-d', strtotime($fecha . ' -45 days')), date('Y-m-d', strtotime($fecha . ' +7 days'))])
                ->whereNotExists(function ($w) {
                    $w->select(DB::raw(1))->from('pagos_egresos as pe')->whereColumn('pe.egreso_id', 'e.id');
                });
            if (Schema::hasColumn('egresos', 'reconciliado_con_id')) {
                $q->whereNull('e.reconciliado_con_id');
            }
            foreach ($q->select('e.id', 'e.total', 'e.fecha_egreso', 'p.nombre as proveedor', 'p.rut')->limit(20)->get() as $e) {
                $pts = 2;
                if ($c->rut && $e->rut && self::rut($c->rut) === self::rut($e->rut)) {
                    $pts += 2;
                } elseif (self::mismoNombre(trim($c->nombre . ' ' . $c->proveedor), $e->proveedor, 1)) {
                    $pts += 1;
                }
                $pts += abs(strtotime($e->fecha_egreso) - strtotime($fecha)) <= 7 * 86400 ? 1 : 0;
                $out[] = ['clase' => 'factura', 'id' => $e->id, 'puntos' => $pts,
                    'label' => 'Factura de ' . ($e->proveedor ?: 'sin proveedor') . ' del ' . date('d-m', strtotime($e->fecha_egreso)) . ' (egreso #' . $e->id . ')'];
            }
        }

        usort($out, function ($a, $b) {
            return $b['puntos'] <=> $a['puntos'];
        });
        return array_values(array_filter($out, function ($x) {
            return !$this->yaUsado($x);
        }));
    }

    /** Persona del equipo a la que va el pago: por RUT, por N° de cuenta o por nombre. */
    public function persona($c, &$como = null)
    {
        $como = null;
        static $users = null;
        if ($users === null) {
            $users = Schema::hasTable('users')
                ? DB::table('users')->select('id', 'name', 'rut', 'numero_cuenta_bancaria')->get() : collect();
        }
        if ($c->rut) {
            foreach ($users as $u) {
                if ($u->rut && self::rut($u->rut) === self::rut($c->rut)) {
                    $como = 'rut';
                    return $u;
                }
            }
        }
        if ($c->cuenta && strlen($c->cuenta) >= 6) {
            foreach ($users as $u) {
                $n = ltrim(preg_replace('/\D/', '', (string) $u->numero_cuenta_bancaria), '0');
                if ($n && $n === ltrim($c->cuenta, '0')) {
                    $como = 'cuenta';
                    return $u;
                }
            }
        }
        $hit = null;
        foreach ($users as $u) {
            if (self::mismoNombre($c->nombre, $u->name)) {
                if ($hit) {
                    return null; // ambiguo
                }
                $hit = $u;
            }
        }
        $como = $hit ? 'nombre' : null;
        return $hit;
    }

    /** [categoria_id, subcategoria_id] de una subcategoría del árbol nuevo, por nombre. */
    private function subId(string $cat, string $sub): ?array
    {
        $catId = DB::table('categorias_compras')->where('nombre', $cat)->value('id');
        $subId = $catId ? DB::table('subcategorias_compras')->where('categoria_id', $catId)->where('nombre', $sub)->value('id') : null;
        return $subId ? [(int) $catId, (int) $subId] : null;
    }

    private function yaUsado(array $x): bool
    {
        if ($x['clase'] === 'factura') {
            return Schema::hasColumn('fin_correos', 'egreso_id')
                && DB::table('fin_correos')->where('egreso_id', $x['id'])->where('estado', 'calzado')->exists();
        }
        $col = $x['clase'] === 'sueldo' ? 'sueldo_pagado_id' : 'pago_egreso_id';
        return DB::table('fin_correos')->where($col, $x['id'])->where('estado', 'calzado')->exists();
    }

    private function cuentaAbiertaPara($c)
    {
        return DB::table('fin_correos')->where('tipo', 'cuenta')->where('proveedor', $c->proveedor)
            ->where('monto', $c->monto)->whereIn('estado', ['nuevo', 'revisar'])
            ->where('recibido_at', '<=', ($c->fecha ?: date('Y-m-d')) . ' 23:59:59')
            ->orderByDesc('recibido_at')->first();
    }

    private function cuentaPagada($c): array
    {
        if (!$c->monto || !$c->proveedor) {
            return [];
        }
        $pago = DB::table('fin_correos')->whereIn('tipo', ['pago', 'personal', 'compra'])
            ->where('proveedor', $c->proveedor)->where('monto', $c->monto)
            ->where('fecha', '>=', substr((string) $c->recibido_at, 0, 10))
            ->whereNull('correo_id')->orderBy('fecha')->first();
        if (!$pago) {
            return $c->vencimiento && $c->vencimiento < date('Y-m-d')
                ? ['estado' => 'revisar', 'nota' => 'Vencida el ' . date('d-m', strtotime($c->vencimiento)) . ' y sin pago avisado'] : [];
        }
        DB::table('fin_correos')->where('id', $pago->id)->whereIn('estado', ['nuevo', 'revisar'])
            ->update(['estado' => 'calzado', 'vinculo' => 'cuenta', 'correo_id' => $c->id,
                      'nota' => "Paga la cuenta {$c->proveedor}", 'updated_at' => now()]);
        return ['estado' => 'pagado', 'vinculo' => 'cuenta', 'correo_id' => $pago->id,
                'nota' => 'Pagada el ' . date('d-m', strtotime($pago->fecha)) . ' (aviso del banco)'];
    }

    public static function rut(?string $r): string
    {
        return strtoupper(ltrim(preg_replace('/[^0-9kK]/', '', (string) $r), '0'));
    }

    /** Coinciden al menos $min palabras del nombre (sin tildes ni mayúsculas, palabras de 3+ letras). */
    public static function mismoNombre(?string $a, ?string $b, int $min = 2): bool
    {
        if (!$a || !$b) {
            return false;
        }
        $t = function ($s) {
            $s = strtolower(strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e',
                'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n']));
            $p = preg_split('/[^a-z0-9]+/', $s);
            return array_values(array_unique(array_filter($p, function ($w) {
                return strlen($w) >= 3 && !in_array($w, ['spa', 'ltda', 'limitada', 'eirl', 'del', 'los', 'las', 'soc', 'sociedad', 'comercial'], true);
            })));
        };
        $x = $t($a);
        $y = $t($b);
        return count(array_intersect($x, $y)) >= $min;
    }
}
