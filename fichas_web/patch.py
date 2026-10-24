import sys
p = sys.argv[1] + '/app/Services/ProgramaContentBuilder.php'
s = open(p, encoding='utf-8').read()
a = '(de lunes a jueves, reservas desde 10 personas, con 10% de descuento)'
b = '(de lunes a miércoles el día se abre con grupos desde 10 personas; de lunes a jueves, 10% de descuento pagando por transferencia)'
if a not in s:
    print('SIN CAMBIO builder' if b in s else 'AVISO: no encontré el texto del builder'); sys.exit(0)
open(p, 'w', encoding='utf-8').write(s.replace(a, b, 1)); print('OK builder')
