import sys
# Fichas de programas en botacura.cl: quita el aviso "⚠️Desde noviembre atendemos de lunes a domingo…".
p = sys.argv[1] + '/app/Services/ProgramaContentBuilder.php'
s = open(p, encoding='utf-8').read()
import re
n, k = re.subn(r'\n[ \t]*\$html \.= "<strong>⚠️Desde noviembre atendemos[^"]*</strong>";', '', s)
# El "Mínimo N personas" queda como última línea del párrafo: sin <br /> colgando
n = n.replace('{$personaLabel}.</strong><br />";\n        $html .= "</p>\\n";', '{$personaLabel}.</strong>";\n        $html .= "</p>\\n";')
if k == 0:
    print('Builder: ya estaba sin el aviso')
else:
    open(p, 'w', encoding='utf-8').write(n); print('Builder: aviso quitado')
