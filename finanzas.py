import re, sys, shutil, time

BASE = '/var/www/html/botacurapp/'
CTRL = BASE + 'app/Http/Controllers/ReporteFinancieroController.php'
V_UTIL = BASE + 'resources/views/themes/backoffice/pages/reporte/financiero/utilidad.blade.php'
V_ANUAL = BASE + 'resources/views/themes/backoffice/pages/reporte/financiero/anual.blade.php'
V_PERC = BASE + 'resources/views/themes/backoffice/pages/reporte/ingreso/percibido.blade.php'
TS = time.strftime('%Y%m%d%H%M')


def flex(s):
    """Patrón que tolera cualquier indentación entre tokens."""
    return r'\s+'.join(re.escape(t) for t in s.split())


def sub1(txt, pat, repl, nombre, flags=0):
    n = len(re.findall(pat, txt, flags))
    if n != 1:
        sys.exit('ERROR: "%s" aparece %d veces (se esperaba 1). No se cambió nada.' % (nombre, n))
    return re.sub(pat, lambda m: repl(m) if callable(repl) else repl, txt, count=1, flags=flags)


ctrl = open(CTRL, encoding='utf-8').read()
util = open(V_UTIL, encoding='utf-8').read()
perc = open(V_PERC, encoding='utf-8').read()

# ── 1. Utilidad (resultado por fecha de visita): sumar abonos extra ──
# AbonoExtraController los descuenta de total_pagar, asi que NO estan en
# abono_programa ni en diferencia_programa. Van al mes de la visita.
if 'totalF29' in ctrl:
    sys.exit('ERROR: el parche ya estaba aplicado. No se cambio nada.')
ctrl = sub1(ctrl, flex('$totalIngresos = $abonos + $consumos + $servicios + $directas;'), lambda m: (
    "// Abonos extra: pagos adicionales antes de la visita. AbonoExtraController los\n"
    "        // descuenta de total_pagar, asi que NO estan en abono_programa ni en\n"
    "        // diferencia_programa. Se imputan al mes de la visita, igual que el abono.\n"
    "        $abonosExtra = Schema::hasTable('abonos_extra') ? (int) DB::table('abonos_extra')\n"
    "            ->join('ventas', 'abonos_extra.id_venta', '=', 'ventas.id')\n"
    "            ->join('reservas', 'ventas.id_reserva', '=', 'reservas.id')\n"
    "            ->whereYear('reservas.fecha_visita', $anio)\n"
    "            ->whereMonth('reservas.fecha_visita', $mes)\n"
    "            ->sum('abonos_extra.monto') : 0;\n"
    "        $abonos += $abonosExtra;\n\n"
    "        " + m.group(0)), 'totalIngresos en calcularUtilidadMes')

# ── 2. Utilidad: total a pagar F29 (IVA 089 + retencion 151 + PPM 062) ──
ctrl = sub1(ctrl, flex("return view('themes.backoffice.pages.reporte.financiero.utilidad', compact("), lambda m: (
    "// Total a pagar en el F29: IVA (089) + retencion de honorarios (151) + PPM (062)\n"
    "        $totalF29 = (int) $ivaAPagarSii + (int) $honorariosRetencion + (int) $ppm;\n\n"
    "        " + m.group(0)), 'return view utilidad')
ctrl = sub1(ctrl, flex("'remanenteAntSii', 'ultimaSyncSii'"),
            "'remanenteAntSii', 'ultimaSyncSii', 'totalF29'", 'compact utilidad')

util = sub1(util, r"(Remanente siguiente \$\{\{[^\n]*\n\s*@endif\s*\n\s*</div>\s*\n\s*</div>)", lambda m: m.group(1) + '''
<div class="row" style="margin:12px 0 0;padding-top:12px;border-top:1px dashed #d1c4e9">
    <div class="col s6 m3">
        <span class="grey-text" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;display:block">Retencion honorarios (Codigo 151)</span>
        <span style="font-size:1.1rem;font-weight:700;color:#B71C1C">${{ number_format($honorariosRetencion,0,',','.') }}</span>
    </div>
    <div class="col s6 m3">
        <span class="grey-text" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;display:block">PPM (Codigo 062)</span>
        <span style="font-size:1.1rem;font-weight:700;color:#B71C1C">${{ number_format($ppm,0,',','.') }}</span>
        @if($ivaEstimado)<span style="font-size:.75rem;display:block;color:#FF8F00">Estimado (sin sync SII)</span>@endif
    </div>
    <div class="col s12 m6">
        <span class="grey-text" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;display:block">Total a pagar F29 (IVA + retenciones + PPM)</span>
        <span style="font-size:1.4rem;font-weight:700;color:#B71C1C">${{ number_format($totalF29,0,',','.') }}</span>
        <span style="font-size:.75rem;display:block;color:#9e9e9e">${{ number_format($ivaAPagarSii,0,',','.') }} + ${{ number_format($honorariosRetencion,0,',','.') }} + ${{ number_format($ppm,0,',','.') }}</span>
    </div>
</div>''', 'bloque IVA SII en utilidad.blade', re.S)

# ── 3. Resumen anual: mismo calculo que Utilidad ──
ctrl = sub1(ctrl, flex("return view('themes.backoffice.pages.reporte.financiero.anual', compact("), lambda m: (
    "// Mismas cifras que Finanzas > Utilidad (una sola formula para todo)\n"
    "        $filas = [];\n"
    "        for ($m = 1; $m <= 12; $m++) {\n"
    "            $d = $this->calcularUtilidadMes((int) $anio, $m);\n"
    "            $filas[$m] = ($d['totalIngresos'] === 0 && $d['totalEgresos'] === 0 && Carbon::create((int) $anio, $m, 1)->isFuture()) ? null : $d;\n"
    "        }\n\n"
    "        " + m.group(0)), 'return view anual')
ctrl = sub1(ctrl, flex("'egresos', 'sueldos', 'bonos', 'impuestos', 'ventasDirectas', 'anio'"),
            "'egresos', 'sueldos', 'bonos', 'impuestos', 'ventasDirectas', 'anio', 'filas'", 'compact anual')

# ── 4. Ingresos percibidos: cada pago en la fecha en que se recibe ──
ini = ctrl.index('public function ingresosPercibidos')
fin = ctrl.index('public function utilidad')
p = ctrl[ini:fin]

# 4a. Diferencias, consumos y servicios se pagan el dia de la visita
a = p.index('// DIFERENCIAS')
b = p.index('$inicioMes', a)
p = p[:a] + p[a:b].replace('reservas.created_at', 'reservas.fecha_visita') + p[b:]

# 4b. Ventas directas por su fecha (antes agrupaba por created_at completo y se perdian filas)
p = sub1(p, r"\$ventasDirectas\s*=\s*DB::table\('ventas_directas'\).*?->get\(\);",
         "$ventasDirectas = DB::table('ventas_directas')\n"
         "            ->selectRaw('YEARWEEK(fecha, 1) as yearweek, DATE(fecha) as fecha, SUM(subtotal) as total')\n"
         "            ->whereBetween('fecha', [$inicioMes, $finMes])\n"
         "            ->groupBy('yearweek', 'fecha')\n"
         "            ->orderBy('fecha')\n"
         "            ->get();", 'ventasDirectas percibidos', re.S)

# 4c. Medios de pago con el mismo criterio de fechas
a = p.index('$total_pago1')
b = p.index('$poroPoro', a)
seg = p[a:b]
c = seg.index('$ventaDirecta')
seg = (seg[:c].replace("whereYear('created_at'", "whereYear('fecha_visita'").replace("whereMonth('created_at'", "whereMonth('fecha_visita'")
       + seg[c:].replace("whereYear('created_at'", "whereYear('fecha'").replace("whereMonth('created_at'", "whereMonth('fecha'"))
p = p[:a] + seg + p[b:]

# 4d. Resumen del mes calculado con TODAS las ventas (antes usaba una pagina de 20)
p = sub1(p, flex("return view('themes.backoffice.pages.reporte.ingreso.percibido', compact("), lambda m: (
    "$sumaTotal = function ($c) { return (int) collect($c)->sum('total'); };\n"
    "        $resumenMes = [\n"
    "            'abonos'      => $sumaTotal($abonos),\n"
    "            'diferencias' => $sumaTotal($diferencias),\n"
    "            'consumos'    => $sumaTotal($consumos) + $sumaTotal($ventasDirectas),\n"
    "            'servicios'   => $sumaTotal($servicios),\n"
    "        ];\n"
    "        $resumenMes['total'] = array_sum($resumenMes);\n"
    "        $resumenMes['pagos'] = (int) ($tiposTransacciones->sum('total_abonos') + $tiposTransacciones->sum('total_diferencias') + $tiposTransacciones->sum('venta_directa'));\n\n"
    "        " + m.group(0)), 'return view percibido')
p = sub1(p, flex("'ventas', 'fechasDisponibles'"), "'ventas', 'fechasDisponibles', 'resumenMes'", 'compact percibido')
ctrl = ctrl[:ini] + p + ctrl[fin:]

perc = sub1(perc, r"\{\{-- Resumen del d.*?(?=\{\{-- Poro Poro --\}\})", '''{{-- Resumen del mes (mismas cifras que la tabla semanal) --}}
<div class="col s12 m3">
    <h5><strong>Resumen del mes</strong></h5>
    <table class="striped bordered">
        <thead>
            <tr>
                <th class="white-text" style="background-color: #039B7B;">Concepto</th>
                <th class="white-text" style="background-color: #039B7B;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Abonos (incluye abonos extra)</td><td>${{ number_format($resumenMes['abonos'],0,'','.') }}</td></tr>
            <tr><td>Diferencias</td><td>${{ number_format($resumenMes['diferencias'],0,'','.') }}</td></tr>
            <tr><td>Consumos y ventas directas</td><td>${{ number_format($resumenMes['consumos'],0,'','.') }}</td></tr>
            <tr><td>Servicios extra</td><td>${{ number_format($resumenMes['servicios'],0,'','.') }}</td></tr>
            <tr><td><strong>Total del mes</strong></td><td><strong>${{ number_format($resumenMes['total'],0,'','.') }}</strong></td></tr>
            <tr><td>Pagos registrados por medio de pago</td><td>${{ number_format($resumenMes['pagos'],0,'','.') }}</td></tr>
            @php $descuadre = $resumenMes['total'] - $resumenMes['pagos']; @endphp
            <tr>
                <td>Descuadre</td>
                <td class="{{ abs($descuadre) > 0 ? 'orange-text' : 'green-text' }}">{{ $descuadre < 0 ? '-' : '' }}${{ number_format(abs($descuadre),0,'','.') }}</td>
            </tr>
        </tbody>
    </table>
</div>

''', 'bloque Resumen del dia', re.S)

# ── Respaldos y escritura ──
for f in (CTRL, V_UTIL, V_ANUAL, V_PERC):
    shutil.copy(f, f + '.bak.' + TS)
open(CTRL, 'w', encoding='utf-8').write(ctrl)
open(V_UTIL, 'w', encoding='utf-8').write(util)
open(V_PERC, 'w', encoding='utf-8').write(perc)
open(V_ANUAL, 'w', encoding='utf-8').write(open('/tmp/anual.blade.php', encoding='utf-8').read())
print('OK. Respaldos con sufijo .bak.' + TS)
