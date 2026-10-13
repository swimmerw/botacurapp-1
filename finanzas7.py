import re, sys, shutil, time, os
# Utilidad: la fila "Gift Cards vendidas" se muestra siempre, aunque el mes este en $0.
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
V = B + 'resources/views/themes/backoffice/pages/reporte/financiero/utilidad.blade.php'
t = open(V, encoding='utf-8').read()
if "=== 'Gift Cards vendidas'" in t:
    sys.exit('Ya estaba aplicado.')
pat = r"@if\(\$row\[1\]\s*>\s*0\)"
n = len(re.findall(pat, t))
if n != 1:
    sys.exit('ERROR: condicion de filas aparece %d veces. No se cambio nada.' % n)
t = re.sub(pat, "@if($row[1] > 0 || $row[0] === 'Gift Cards vendidas')", t, count=1)
shutil.copy(V, V + '.bak.' + time.strftime('%Y%m%d%H%M'))
open(V, 'w', encoding='utf-8').write(t)
print('OK')
