import sys
p = sys.argv[1] + '/app/Services/ProgramaContentBuilder.php'
s = open(p, encoding='utf-8').read()
a = '$html .= "<strong>⚠️Atendemos de Jueves a Domingo.</strong>";'
if a not in s:
    print('SIN CAMBIO builder'); sys.exit(0)
s = s.replace(a, '$html .= "<strong>⚠️Desde noviembre atendemos de lunes a domingo, de 10:00 a 19:00 hrs (de lunes a jueves, reservas desde 10 personas, con 10% de descuento).</strong>";', 1)
open(p, 'w', encoding='utf-8').write(s); print('OK builder')
