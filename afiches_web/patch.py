import sys
p = sys.argv[1] + '/app/Services/ProgramaContentBuilder.php'
s = open(p, encoding='utf-8').read()
a = '$html .= "<strong>✅Desde {$minPersonas} {$personaLabel}.</strong><br />";'
if a in s:
    s = s.replace(a, '$html .= "<strong>✅Mínimo {$minPersonas} {$personaLabel}.</strong><br />";', 1)
    open(p, 'w', encoding='utf-8').write(s); print('OK ficha: Mínimo N personas')
else:
    print('SIN CAMBIO ficha')
