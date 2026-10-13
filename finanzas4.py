import re, sys, shutil, time
CTRL = '/var/www/html/botacurapp/app/Http/Controllers/ReporteFinancieroController.php'
ctrl = open(CTRL, encoding='utf-8').read()
if 'ventas.fecha_abono' in ctrl:
    sys.exit('Codigo ya actualizado.')
ini = ctrl.index('public function ingresosPercibidos')
fin = ctrl.index('public function utilidad')
p = ctrl[ini:fin]
F = "COALESCE(ventas.fecha_abono, DATE(reservas.created_at))"

# Abonos diarios por fecha real de abono
a = p.index('// ABONOS'); b = p.index('// ABONOS EXTRA', a)
seg = p[a:b]
for viejo, nuevo in [
    ("DATE(reservas.created_at)", F),
    ("YEARWEEK(reservas.created_at, 1)", "YEARWEEK(%s, 1)" % F),
]:
    if viejo not in seg:
        sys.exit('ERROR: no encontre "%s" en ABONOS. No se cambio nada.' % viejo)
    seg = seg.replace(viejo, nuevo)
seg, n1 = re.subn(r"->whereYear\('reservas\.created_at',\s*\$anio\)", "->whereRaw('YEAR(%s) = ?', [$anio])" % F, seg)
seg, n2 = re.subn(r"->whereMonth\('reservas\.created_at',\s*\$mes\)", "->whereRaw('MONTH(%s) = ?', [$mes])" % F, seg)
if (n1, n2) != (1, 1):
    sys.exit('ERROR: filtros de ABONOS (%d,%d). No se cambio nada.' % (n1, n2))
p = p[:a] + seg + p[b:]

# Medios de pago: abono por fecha real de abono
G = "COALESCE(ventas.fecha_abono, (SELECT DATE(r2.created_at) FROM reservas r2 WHERE r2.id = ventas.id_reserva))"
pat = r"(\$abono\s*=\s*Venta::where\('id_tipo_transaccion_abono',\s*\$tipo->id\).*?)->whereHas\('reserva',\s*function\s*\(\$query\)\s*use\s*\(\$anio,\s*\$mes\)\s*\{.*?\}\)"
if len(re.findall(pat, p, re.S)) != 1:
    sys.exit('ERROR: no encontre el abono por medio de pago. No se cambio nada.')
p = re.sub(pat, lambda m: m.group(1) + "->whereRaw('YEAR(%s) = ? AND MONTH(%s) = ?', [$anio, $mes])" % (G, G), p, count=1, flags=re.S)

ctrl = ctrl[:ini] + p + ctrl[fin:]
shutil.copy(CTRL, CTRL + '.bak.' + time.strftime('%Y%m%d%H%M'))
open(CTRL, 'w', encoding='utf-8').write(ctrl)
print('Codigo OK')
