import os
inc = "@include('themes.backoffice.pages.reserva.includes._bandera_bot', ['r' => {v}])"
cambios = [
  ('resources/views/themes/backoffice/pages/reserva/screens/principal.blade.php', "<strong>{{ addslashes($reserva->cliente->nombre_cliente) }} -</strong>", '$reserva'),
  ('resources/views/themes/backoffice/pages/reserva/screens/mobile.blade.php', "                                            {{ $reserva->cliente->nombre_cliente }}", '$reserva'),
  ('resources/views/themes/backoffice/pages/reserva/screens/alternative.blade.php', "{{ $reserva->cliente->nombre_cliente}}", '$reserva'),
  ('resources/views/themes/backoffice/pages/reserva/index_registro.blade.php', "{{($reserva->venta->tiene_gc) ? '🎁' : ''}} {{$reserva->cliente->nombre_cliente}}", '$reserva'),
  ('resources/views/themes/backoffice/pages/reporte/reservas_dia.blade.php', "<td>{{ $r->nombre_cliente }}</td>", '$r'),
]
for p, ancla, var in cambios:
    if not os.path.exists(p):
        print('  no existe:', p); continue
    s = open(p, encoding='utf-8').read()
    if '_bandera_bot' in s:
        print('  ya estaba:', p.split('/')[-1]); continue
    n = s.count(ancla)
    if not n:
        print('  sin cambios (no encontré dónde va):', p.split('/')[-1]); continue
    if ancla.startswith('<td>'):
        nuevo = ancla.replace('<td>', '<td>' + inc.format(v=var), 1)
    else:
        nuevo = ancla.replace(ancla.strip(), inc.format(v=var) + ancla.strip(), 1)
    s = s.replace(ancla, nuevo)
    open(p, 'w', encoding='utf-8').write(s)
    print(f'  bandera agregada ({n}):', p.split('/')[-1])
