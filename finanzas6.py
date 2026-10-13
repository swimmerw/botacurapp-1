import re, sys, shutil, time, os
# Utilidad: la Gift Card es ingreso el mes en que se VENDE (igual que Ingresos percibidos y el SII).
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
CTRL = B + 'app/Http/Controllers/ReporteFinancieroController.php'
V_UTIL = B + 'resources/views/themes/backoffice/pages/reporte/financiero/utilidad.blade.php'
ctrl = open(CTRL, encoding='utf-8').read()
util = open(V_UTIL, encoding='utf-8').read()
if '$abonos + $giftCardsVendidasMes' in ctrl:
    sys.exit('Ya estaba aplicado.')

def flex(s):
    return r'\s*'.join(re.escape(t) for t in s.split())

def sub1(txt, pat, repl, nombre, flags=0):
    n = len(re.findall(pat, txt, flags))
    if n != 1:
        sys.exit('ERROR: "%s" aparece %d veces (se esperaba 1). No se cambio nada.' % (nombre, n))
    return re.sub(pat, lambda m: repl, txt, count=1, flags=flags)

ctrl = sub1(ctrl, flex("$totalIngresos = $abonos + $giftCardsUsadas + $consumos + $servicios + $directas;"),
    "// La Gift Card es ingreso el mes en que se vende (boleta al cobrarla). El programa\n"
    "        // canjeado con Gift Card ya se resto de los abonos para no contarlo dos veces.\n"
    "        $totalIngresos = $abonos + $giftCardsVendidasMes + $consumos + $servicios + $directas;",
    'totalIngresos')
ctrl = sub1(ctrl, flex("// Vendidas en el mes: informativo (es un anticipo, se reconoce al usarse)"),
    "// Gift Cards vendidas en el mes: suman en los ingresos del mes de la venta", 'comentario vendidas')

util = sub1(util, flex("['Programas pagados con Gift Card', $giftCardsUsadas],"),
    "['Gift Cards vendidas', $giftCardsVendidasMes],", 'fila gift cards')
util = sub1(util, r"@if\(\$giftCardsVendidasCant > 0\).*?@endif", '''@if($giftCardsVendidasCant > 0 || $giftCardsUsadas > 0)
                            <tr>
                                <td colspan="3" style="padding:6px 0 0;color:#9e9e9e;font-size:.78rem">
                                    @if($giftCardsVendidasCant > 0)Gift Cards vendidas en {{ $nombreMes }}: {{ $giftCardsVendidasCant }} (mismo monto que en Ingresos percibidos).@endif
                                    @if($giftCardsUsadas > 0)Programas canjeados con Gift Card este mes: ${{ number_format($giftCardsUsadas,0,',','.') }}. No vuelven a sumar: ya se contaron cuando se vendio la Gift Card.@endif
                                </td>
                            </tr>
                            @endif''', 'nota gift cards', re.S)

ts = time.strftime('%Y%m%d%H%M')
for f in (CTRL, V_UTIL):
    shutil.copy(f, f + '.bak.' + ts)
open(CTRL, 'w', encoding='utf-8').write(ctrl)
open(V_UTIL, 'w', encoding='utf-8').write(util)
print('OK. Respaldos con sufijo .bak.' + ts)
