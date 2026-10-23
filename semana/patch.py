import sys, re
base = sys.argv[1]; aqui = sys.argv[2]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read(); n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)

def turnos(s):
    if 'function mensajesSemana' in s: return None
    m = open(aqui + '/metodos.php.txt', encoding='utf-8').read()
    i = s.rstrip().rfind('}')
    return s[:i].rstrip() + '\n' + m + '}\n'
rw('app/Equipo/Turnos.php', turnos)

def ctrl(s):
    if 'function resumenSemana' in s: return None
    a = '    public function guardarEquipo(Request $request)'
    if a not in s: raise SystemExit('ERROR: controlador de turnos distinto')
    b = '''    public function resumenSemana()
    {
        \\Artisan::call('equipo:resumen-semana', ['--enviar' => true, '--forzar' => true]);
        return back()->with('success', 'Resumen de la semana: ' . mb_substr(trim(\\Artisan::output()), -160));
    }

    public function avisarEquipo()
    {
        \\Artisan::call('equipo:resumen-semana', ['--trabajadores' => true]);
        $out = trim(\\Artisan::output());
        $i = mb_strrpos($out, 'Equipo:');
        return back()->with('success', $i !== false ? mb_substr($out, $i, 400) : 'Listo.');
    }

'''
    return s.replace(a, b + a, 1)
rw('app/Http/Controllers/EquipoTurnosController.php', ctrl)

def rutas(s):
    if 'resumenSemana' in s: return None
    a = "    Route::post('/avisar', 'EquipoTurnosController@avisar')->name('.avisar');"
    if a not in s: raise SystemExit('ERROR: rutas de turnos distintas')
    return s.replace(a, a + "\n    Route::post('/resumen-semana', 'EquipoTurnosController@resumenSemana')->name('.resumen');\n    Route::post('/avisar-equipo', 'EquipoTurnosController@avisarEquipo')->name('.avisar_equipo');", 1)
rw('routes/equipo.php', rutas)

def vista(s):
    if 'turnos.resumen' in s: return None
    a = '<form method="POST" action="{{ route(\'backoffice.equipo.turnos.avisar\') }}" style="display:inline">@csrf<button class="pill" style="cursor:pointer">Enviar aviso de mañana ahora</button></form>'
    if a not in s: raise SystemExit('ERROR: vista de turnos distinta')
    b = ('\n            <form method="POST" action="{{ route(\'backoffice.equipo.turnos.resumen\') }}" style="display:inline">@csrf<button class="pill" style="cursor:pointer">Enviar resumen de la semana</button></form>'
         '\n            <form method="POST" action="{{ route(\'backoffice.equipo.turnos.avisar_equipo\') }}" style="display:inline" onsubmit="return confirm(\'Se enviará a cada persona sus turnos de esta semana (los aceptados; si un día no está aceptado, lo propuesto). ¿Enviar?\')">@csrf<button class="pill" style="cursor:pointer;background:#039B7B;color:#fff">Avisar turnos al equipo</button></form>')
    s = s.replace(a, a + b, 1)
    return s.replace('El aviso del día siguiente sale a las 15:00 por WhatsApp.', 'El aviso del día siguiente sale a las 15:00 por WhatsApp, y el resumen de la semana a las 14:55 del día antes del primer día con reservas.', 1)
rw('resources/views/themes/backoffice/pages/equipo_turnos/index.blade.php', vista)

def kernel(s):
    if 'equipo:resumen-semana' in s: return None
    m = re.search(r'protected function schedule\(Schedule \$schedule\)\s*\{\n', s)
    if not m: raise SystemExit('ERROR: Kernel distinto')
    return s[:m.end()] + "        // Resumen de la semana (reservas, programas y equipo) el día antes del primer día con reservas\n        $schedule->command('equipo:resumen-semana --enviar')->dailyAt('14:55')->timezone('America/Santiago');\n" + s[m.end():]
rw('app/Console/Kernel.php', kernel)
