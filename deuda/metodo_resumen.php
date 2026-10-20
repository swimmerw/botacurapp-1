    /**
     * Cuadro "Mi deuda": total pendiente, por tipo, mes a mes (lo que se sumó, lo que se pagó y el saldo al cierre)
     * y lo que se suma cada mes (cuotas de créditos, cuentas del correo e impuestos).
     */
    public function resumen(array $ambitos): array
    {
        $deudas = DB::table('fin_deudas')->whereIn('ambito', $ambitos)->where('estado', '<>', 'anulada')->get();
        $ids = $deudas->pluck('id')->all();
        $pagos = $ids ? DB::table('fin_deuda_pagos')->whereIn('deuda_id', $ids)->get(['deuda_id', 'monto', 'fecha']) : collect();
        $pagadoPor = [];
        foreach ($pagos as $p) {
            $pagadoPor[$p->deuda_id] = ($pagadoPor[$p->deuda_id] ?? 0) + (int) $p->monto;
        }
        $tipos = ['credito' => 'Créditos', 'deuda' => 'Deudas', 'impuesto' => 'Impuestos', 'cuenta' => 'Cuentas'];
        $porTipo = [];
        $porAmbito = [];
        $total = 0;
        foreach ($deudas as $d) {
            if ($d->estado !== 'pendiente') {
                continue;
            }
            $s = max(0, (int) $d->monto_total - ($pagadoPor[$d->id] ?? 0));
            $total += $s;
            $t = $tipos[$d->tipo] ?? ucfirst($d->tipo);
            if (!isset($porTipo[$t])) {
                $porTipo[$t] = ['total' => 0, 'n' => 0, 'items' => []];
            }
            $porTipo[$t]['total'] += $s;
            $porTipo[$t]['n']++;
            $porTipo[$t]['items'][] = ['nombre' => $d->nombre, 'saldo' => $s];
            $porAmbito[$d->ambito] = ($porAmbito[$d->ambito] ?? 0) + $s;
        }
        uasort($porTipo, function ($a, $b) { return $b['total'] <=> $a['total']; });

        // Mes a mes desde que se empezó a llevar (las deudas cargadas el primer día son el saldo inicial)
        $meses = [];
        $inicial = 0;
        $primerDia = $deudas->min('created_at') ? substr($deudas->min('created_at'), 0, 10) : date('Y-m-d');
        foreach ($deudas as $d) {
            $dia = substr((string) $d->created_at, 0, 10);
            if ($dia === $primerDia) {
                $inicial += (int) $d->monto_total;
                continue;
            }
            $m = substr($dia, 0, 7);
            $meses[$m]['suma'] = ($meses[$m]['suma'] ?? 0) + (int) $d->monto_total;
        }
        foreach ($pagos as $p) {
            $m = substr((string) $p->fecha, 0, 7);
            $meses[$m]['pago'] = ($meses[$m]['pago'] ?? 0) + (int) $p->monto;
        }
        $desde = \Carbon\Carbon::parse(min($primerDia, $pagos->min('fecha') ?: $primerDia))->startOfMonth();
        $filas = [];
        $saldo = $inicial;
        for ($c = $desde->copy(); $c->lte(now()->startOfMonth()); $c->addMonth()) {
            $k = $c->format('Y-m');
            $suma = $meses[$k]['suma'] ?? 0;
            $pago = $meses[$k]['pago'] ?? 0;
            $saldo += $suma - $pago;
            $filas[] = ['mes' => ucfirst($c->locale('es')->isoFormat('MMMM YYYY')), 'suma' => $suma, 'pago' => $pago, 'saldo' => $saldo];
        }

        // Lo que se suma cada mes (aprox.)
        $cuotas = $deudas->where('estado', 'pendiente')->where('tipo', 'credito')->sum('cuota');
        $hace3 = now()->subMonths(3)->toDateString();
        $cuentas = (int) round($deudas->where('tipo', 'cuenta')->filter(function ($d) use ($hace3) {
            return substr((string) $d->created_at, 0, 10) >= $hace3;
        })->sum('monto_total') / 3);
        $imp = $deudas->where('tipo', 'impuesto')->sortByDesc('created_at')->first();
        $mensual = array_filter([
            'Cuotas de créditos' => (int) $cuotas,
            'Cuentas que llegan al correo (promedio)' => $cuentas,
            'Impuestos (último F29 registrado)' => $imp ? (int) $imp->monto_total : 0,
        ]);
        return ['total' => $total, 'por_tipo' => $porTipo, 'por_ambito' => $porAmbito, 'inicial' => $inicial,
                'desde' => $primerDia, 'meses' => $filas, 'mensual' => $mensual, 'mensual_total' => array_sum($mensual)];
    }

