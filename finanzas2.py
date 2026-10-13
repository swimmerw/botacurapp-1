import re, sys, shutil, time
CTRL = '/var/www/html/botacurapp/app/Http/Controllers/ReporteFinancieroController.php'
ctrl = open(CTRL, encoding='utf-8').read()
if 'no se ha cobrado' in ctrl:
    sys.exit('Ya estaba aplicado.')
ini = ctrl.index('public function ingresosPercibidos')
a = ctrl.index('// DIFERENCIAS', ini)
b = ctrl.index('$inicioMes', a)
seg = ctrl[a:b]
pat = r"(->whereMonth\('reservas\.fecha_visita',\s*\$mes\))"
n = len(re.findall(pat, seg))
if n != 3:
    sys.exit('ERROR: se esperaban 3 consultas y hay %d. No se cambio nada.' % n)
seg = re.sub(pat, r"\1\n            ->whereDate('reservas.fecha_visita', '<=', now()->toDateString()) // visita futura: aun no se ha cobrado", seg)
shutil.copy(CTRL, CTRL + '.bak.' + time.strftime('%Y%m%d%H%M'))
open(CTRL, 'w', encoding='utf-8').write(ctrl[:a] + seg + ctrl[b:])
print('OK')
