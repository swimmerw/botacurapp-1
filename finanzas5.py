import re, sys, shutil, time, os

# Gift Cards por separado (13/10/2026):
#  - Utilidad: fila "Programas pagados con Gift Card" (sale de Programas / Abonos,
#    sigue sumando en el total) + nota con las Gift Cards vendidas del mes.
#  - Ingresos percibidos: columna "Gift Cards" en la tabla semanal (suma en el total
#    de la semana) y fila propia en el Resumen del mes.
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
CTRL = B + 'app/Http/Controllers/ReporteFinancieroController.php'
V_UTIL = B + 'resources/views/themes/backoffice/pages/reporte/financiero/utilidad.blade.php'
V_PERC = B + 'resources/views/themes/backoffice/pages/reporte/ingreso/percibido.blade.php'

ctrl = open(CTRL, encoding='utf-8').read()
util = open(V_UTIL, encoding='utf-8').read()
perc = open(V_PERC, encoding='utf-8').read()
if 'giftCardsUsadas' in ctrl:
    sys.exit('Ya estaba aplicado.')


def flex(s):
    return r'\s*'.join(re.escape(t) for t in s.split())


def sub1(txt, pat, repl, nombre, flags=0):
    n = len(re.findall(pat, txt, flags))
    if n != 1:
        sys.exit('ERROR: "%s" aparece %d veces (se esperaba 1). No se cambio nada.' % (nombre, n))
    return re.sub(pat, lambda m: repl(m) if callable(repl) else repl, txt, count=1, flags=flags)


# ══ Controlador: Ingresos percibidos ══
ini = ctrl.index('public function ingresosPercibidos')
fin = ctrl.index('public function utilidad')
p = ctrl[ini:fin]
# Gift Cards en su propia columna (ya no mezcladas con los abonos)
p = sub1(p, flex("$abonos = $this->mergeTotalesPorFecha($abonos, $giftCardsVendidas);"),
         "// Gift Cards: columna propia en la tabla semanal (no se mezclan con los abonos)",
         'merge gift cards en abonos')
p = sub1(p, flex("->where('monto', '>', 0) ->groupBy('yearweek', 'fecha') ->get();"),
         "->where('monto', '>', 0)\n            ->groupBy('yearweek', 'fecha')\n            ->orderBy('fecha')\n            ->get();",
         'consulta gift cards vendidas')
p = sub1(p, flex("'servicios' => $sumaTotal($servicios), ];"),
         "'servicios'   => $sumaTotal($servicios),\n            'giftcards'   => $totalGiftCardsVendidas,\n        ];",
         'resumenMes servicios')
p = sub1(p, flex("'fechasDisponibles', 'resumenMes'"), "'fechasDisponibles', 'resumenMes', 'giftCardsVendidas'",
         'compact percibido')
ctrl = ctrl[:ini] + p + ctrl[fin:]

# ══ Controlador: Utilidad ══
ini = ctrl.index('private function calcularUtilidadMes')
fin = ctrl.index('public function comparar')
c = ctrl[ini:fin]
c = sub1(c, flex("$totalIngresos = $abonos + $consumos + $servicios + $directas;"), lambda m: (
    "// Gift Cards: el programa pagado con Gift Card cuenta como ingreso el mes de la\n"
    "        // visita (cuando se presta el servicio). Se muestra en su propia fila.\n"
    "        $giftCardsUsadas = (int) DB::table('ventas')\n"
    "            ->join('reservas', 'ventas.id_reserva', '=', 'reservas.id')\n"
    "            ->whereYear('reservas.fecha_visita', $anio)\n"
    "            ->whereMonth('reservas.fecha_visita', $mes)\n"
    "            ->whereExists(function ($q) { $q->select(DB::raw(1))->from('gift_cards')->whereColumn('gift_cards.id_venta', 'ventas.id'); })\n"
    "            ->sum('ventas.abono_programa');\n"
    "        $abonos -= $giftCardsUsadas;\n"
    "        // Vendidas en el mes: informativo (es un anticipo, se reconoce al usarse)\n"
    "        $gcVendidas = DB::table('gift_cards')\n"
    "            ->whereBetween(DB::raw('DATE(created_at)'), [$inicio, $fin])\n"
    "            ->where('monto', '>', 0)\n"
    "            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto), 0) as total')\n"
    "            ->first();\n"
    "        $giftCardsVendidasMes = (int) $gcVendidas->total;\n"
    "        $giftCardsVendidasCant = (int) $gcVendidas->cantidad;\n\n"
    "        $totalIngresos = $abonos + $giftCardsUsadas + $consumos + $servicios + $directas;"),
    'totalIngresos en calcularUtilidadMes')
c = sub1(c, flex("return compact( 'abonos', 'consumos',"),
         "return compact(\n            'abonos', 'giftCardsUsadas', 'giftCardsVendidasMes', 'giftCardsVendidasCant', 'consumos',",
         'compact calcularUtilidadMes')
ctrl = ctrl[:ini] + c + ctrl[fin:]

ini = ctrl.index('public function utilidad')
fin = ctrl.index('private function calcularUtilidadMes')
u = ctrl[ini:fin]
u = sub1(u, flex("$abonos = $u['abonos'];"),
         "$abonos = $u['abonos'];\n        $giftCardsUsadas = $u['giftCardsUsadas'];\n"
         "        $giftCardsVendidasMes = $u['giftCardsVendidasMes'];\n        $giftCardsVendidasCant = $u['giftCardsVendidasCant'];",
         'variables utilidad')
u = sub1(u, flex("'abonos', 'consumos', 'servicios', 'directas',"),
         "'abonos', 'giftCardsUsadas', 'giftCardsVendidasMes', 'giftCardsVendidasCant', 'consumos', 'servicios', 'directas',",
         'compact utilidad')
ctrl = ctrl[:ini] + u + ctrl[fin:]

# ══ Vista Utilidad ══
util = sub1(util, flex("['Programas / Abonos', $abonos],"),
            "['Programas / Abonos', $abonos],\n                            ['Programas pagados con Gift Card', $giftCardsUsadas],",
            'fila programas')
util = sub1(util, r"(@endforeach\s*</tbody>)(\s*<tfoot>\s*<tr style=\"background:#f1f8e9\">)", lambda m: (
    "@endforeach\n"
    "                            @if($giftCardsVendidasCant > 0)\n"
    "                            <tr>\n"
    "                                <td colspan=\"3\" style=\"padding:6px 0 0;color:#9e9e9e;font-size:.78rem\">\n"
    "                                    Gift Cards vendidas en {{ $nombreMes }}: {{ $giftCardsVendidasCant }} por ${{ number_format($giftCardsVendidasMes,0,',','.') }}.\n"
    "                                    No suman aqui: entran al total cuando el cliente las usa (fila \"Programas pagados con Gift Card\"). La plata recibida esta en Ingresos percibidos.\n"
    "                                </td>\n"
    "                            </tr>\n"
    "                            @endif\n"
    "                            </tbody>" + m.group(2)), 'fin tabla ingresos utilidad')

# ══ Vista Ingresos percibidos ══
perc = sub1(perc, flex("->merge(collect($ventasDirectas)->pluck('yearweek'))"),
            "->merge(collect($ventasDirectas)->pluck('yearweek'))\n->merge(collect($giftCardsVendidas)->pluck('yearweek'))",
            'semanas')
perc = sub1(perc, flex("->merge($ventasDirectas) ->where('yearweek', $week)"),
            "->merge($ventasDirectas)\n->merge($giftCardsVendidas)\n->where('yearweek', $week)", 'fechas de la semana')
perc = sub1(perc, flex("<th>Servicios</th>"), "<th>Servicios</th>\n<th>Gift Cards</th>", 'encabezado')
perc = sub1(perc, flex("+ (optional($servicios->firstWhere('fecha', $fecha))->total ?? 0);"),
            "+ (optional($servicios->firstWhere('fecha', $fecha))->total ?? 0)\n"
            "+ (optional($giftCardsVendidas->firstWhere('fecha', $fecha))->total ?? 0);", 'suma semanal')
perc = sub1(perc, r"(<td>\$\{\{ number_format\(optional\(\$servicios->firstWhere\('fecha', \$fecha\)\)->total \?\? 0, 0, '', '\.'\) \}\}</td>)",
            lambda m: m.group(1) + "\n<td>${{ number_format(optional($giftCardsVendidas->firstWhere('fecha', $fecha))->total ?? 0, 0, '', '.') }}</td>",
            'celda servicios')
perc = sub1(perc, r'<td colspan="4"></td>(\s*<td><strong>Total Semana)', lambda m: '<td colspan="5"></td>' + m.group(1), 'colspan total semana')
perc = sub1(perc, re.escape('<tr><td>Abonos (incluye abonos extra y Gift Cards vendidas)</td>'),
            '<tr><td>Abonos (incluye abonos extra)</td>', 'fila abonos resumen')
perc = sub1(perc, r"(<tr><td>Servicios extra</td><td>[^\n]*</td></tr>)", lambda m: m.group(1) +
            "\n<tr><td>Gift Cards vendidas</td><td>${{ number_format($resumenMes['giftcards'],0,'','.') }}</td></tr>",
            'fila servicios resumen')

ts = time.strftime('%Y%m%d%H%M')
for f in (CTRL, V_UTIL, V_PERC):
    shutil.copy(f, f + '.bak.' + ts)
open(CTRL, 'w', encoding='utf-8').write(ctrl)
open(V_UTIL, 'w', encoding='utf-8').write(util)
open(V_PERC, 'w', encoding='utf-8').write(perc)
print('OK. Respaldos con sufijo .bak.' + ts)
