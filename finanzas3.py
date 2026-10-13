import re, sys, shutil, time
B = '/var/www/html/botacurapp/'
CTRL = B + 'app/Http/Controllers/ReporteFinancieroController.php'
VIEW = B + 'resources/views/themes/backoffice/pages/reporte/ingreso/percibido.blade.php'
ctrl = open(CTRL, encoding='utf-8').read()
view = open(VIEW, encoding='utf-8').read()
if 'giftCardsVendidas' in ctrl:
    sys.exit('Ya estaba aplicado.')

def sub1(txt, pat, repl, nombre, flags=0):
    n = len(re.findall(pat, txt, flags))
    if n != 1:
        sys.exit('ERROR: "%s" aparece %d veces. No se cambio nada.' % (nombre, n))
    return re.sub(pat, lambda m: repl(m) if callable(repl) else repl, txt, count=1, flags=flags)

ini = ctrl.index('public function ingresosPercibidos')
fin = ctrl.index('public function utilidad')
p = ctrl[ini:fin]

NO_GC = ("->whereNotExists(function ($q) { $q->select(DB::raw(1))->from('gift_cards')->whereColumn('gift_cards.id_venta', 'ventas.id'); }) "
         "// pagado con Gift Card: la plata entro el dia en que se vendio la Gift Card")

# a) Abonos de reservas pagadas con Gift Card no son plata nueva
a = p.index('// ABONOS')
b = p.index('// ABONOS EXTRA', a)
seg = sub1(p[a:b], r"(->whereMonth\('reservas\.created_at',\s*\$mes\))", lambda m: m.group(1) + "\n            " + NO_GC, 'abonos percibidos')
p = p[:a] + seg + p[b:]

# b) Gift Cards vendidas: entran a caja el dia de la venta
p = sub1(p, r"\$abonos\s*=\s*\$this->mergeTotalesPorFecha\(\$abonos,\s*\$abonosExtra\);", lambda m: m.group(0) + """

        // GIFT CARDS VENDIDAS (la plata entra el dia en que se vende la Gift Card)
        $giftCardsVendidas = DB::table('gift_cards')
            ->selectRaw('YEARWEEK(created_at, 1) as yearweek, DATE(created_at) as fecha, SUM(monto) as total')
            ->whereYear('created_at', $anio)
            ->whereMonth('created_at', $mes)
            ->where('monto', '>', 0)
            ->groupBy('yearweek', 'fecha')
            ->get();
        $totalGiftCardsVendidas = (int) collect($giftCardsVendidas)->sum('total');
        $abonos = $this->mergeTotalesPorFecha($abonos, $giftCardsVendidas);""", 'merge abonos extra percibidos')

# c) Medios de pago: sin los abonos pagados con Gift Card
p = sub1(p, r"(\$abono\s*=\s*Venta::where\('id_tipo_transaccion_abono',\s*\$tipo->id\))",
         lambda m: m.group(1) + "\n                ->whereNotIn('id', DB::table('gift_cards')->whereNotNull('id_venta')->pluck('id_venta'))", 'abono por tipo')

# d) Resumen: propinas y Gift Cards para explicar la diferencia con los medios de pago
p = sub1(p, r"\$resumenMes\['pagos'\]\s*=.*?;", lambda m: m.group(0) + """
        $resumenMes['giftcards'] = $totalGiftCardsVendidas;
        $resumenMes['propinas'] = (int) round(0.1 * DB::table('detalles_consumos')
            ->join('consumos', 'detalles_consumos.id_consumo', '=', 'consumos.id')
            ->join('ventas', 'consumos.id_venta', '=', 'ventas.id')
            ->join('reservas', 'ventas.id_reserva', '=', 'reservas.id')
            ->whereYear('reservas.fecha_visita', $anio)
            ->whereMonth('reservas.fecha_visita', $mes)
            ->whereDate('reservas.fecha_visita', '<=', now()->toDateString())
            ->where('detalles_consumos.genera_propina', 1)
            ->sum('detalles_consumos.subtotal'));
        // Lo que deberia aparecer en los medios de pago: ingresos + propinas - Gift Cards (no guardan medio de pago)
        $resumenMes['esperado'] = $resumenMes['total'] + $resumenMes['propinas'] - $resumenMes['giftcards'];""", 'resumenMes pagos', re.S)
ctrl = ctrl[:ini] + p + ctrl[fin:]

view = sub1(view, re.escape('<tr><td>Abonos (incluye abonos extra)</td>'),
            '<tr><td>Abonos (incluye abonos extra y Gift Cards vendidas)</td>', 'fila abonos')
view = sub1(view, r"<tr><td>Pagos registrados por medio de pago</td>.*?Descuadre</td>.*?</tr>", '''<tr><td>+ Propinas (del equipo, se cobran con la cuenta)</td><td>${{ number_format($resumenMes['propinas'],0,'','.') }}</td></tr>
            <tr><td>&minus; Gift Cards vendidas (sin medio de pago)</td><td>${{ number_format($resumenMes['giftcards'],0,'','.') }}</td></tr>
            <tr><td>Deberia estar en medios de pago</td><td>${{ number_format($resumenMes['esperado'],0,'','.') }}</td></tr>
            <tr><td>Pagos registrados por medio de pago</td><td>${{ number_format($resumenMes['pagos'],0,'','.') }}</td></tr>
            @php $descuadre = $resumenMes['pagos'] - $resumenMes['esperado']; @endphp
            <tr>
                <td><strong>Descuadre</strong></td>
                <td class="{{ abs($descuadre) > 1000 ? 'orange-text' : 'green-text' }}"><strong>{{ $descuadre < 0 ? '-' : '' }}${{ number_format(abs($descuadre),0,'','.') }}</strong></td>
            </tr>''', 'filas descuadre', re.S)

ts = time.strftime('%Y%m%d%H%M')
shutil.copy(CTRL, CTRL + '.bak.' + ts)
shutil.copy(VIEW, VIEW + '.bak.' + ts)
open(CTRL, 'w', encoding='utf-8').write(ctrl)
open(VIEW, 'w', encoding='utf-8').write(view)
print('OK')
