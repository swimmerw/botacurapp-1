import sys
p = sys.argv[1] + '/resources/views/pdf/cotizacion/viewPDF.blade.php'
s = open(p, encoding='utf-8').read()
if 'grupos de más de 15 personas' in s:
    print('SIN CAMBIO pdf'); sys.exit(0)
a = '<br>\n    <div class="terminos">'
if a not in s: raise SystemExit('ERROR: PDF distinto (no encontre los terminos)')
s = s.replace(a, '<div style="page-break-before: always;"></div>\n    <div class="terminos">', 1)
viejo = '<li>Se permite una única reprogramación, solicitada con al menos 72 horas hábiles de anticipación, sujeta a disponibilidad y para usar dentro de 45 días corridos. No se realizan devoluciones de dinero.</li>'
nuevo = ('<li>Se permite una única reprogramación, sujeta a disponibilidad y para usar dentro de 45 días corridos. Reservas de hasta 15 personas: solicitarla con al menos 72 horas hábiles de anticipación. '
         '<strong>Reservas de grupos de más de 15 personas: no se pueden modificar con 72 horas; los cambios de fecha o de cantidad de personas se solicitan con al menos 10 días de anticipación.</strong> No se realizan devoluciones de dinero.</li>')
if viejo in s:
    s = s.replace(viejo, nuevo, 1)
else:
    s = s.replace('</ol>', '    ' + nuevo + '\n        </ol>', 1)
extra = ('            <li>Atendemos de lunes a domingo, de 10:00 a 19:00 horas, solo con reserva. De lunes a jueves se reciben reservas desde 10 personas.</li>\n'
         '            <li>En noviembre, los programas de lunes a jueves tienen 10% de descuento (pago por transferencia, no reagendables).</li>\n')
s = s.replace('        </ol>', extra + '        </ol>', 1)
s = s.replace('Políticas completas: www.botacura.cl/privacy-policy/', 'Políticas completas: www.botacura.cl/politicas-y-reservacion/')
open(p, 'w', encoding='utf-8').write(s); print('OK pdf')
