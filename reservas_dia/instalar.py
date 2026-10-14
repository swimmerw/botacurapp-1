import re, sys, shutil, time, os
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
RUTAS = B + 'routes/web.php'
MENU = B + 'resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php'
r = open(RUTAS, encoding='utf-8').read()
m = open(MENU, encoding='utf-8').read()
ts = time.strftime('%Y%m%d%H%M')
if 'finanzas.reservas_dia' not in r:
    pat = r"(Route::get\('finanzas/ingresos',\s*'ReporteFinancieroController@ingresosPercibidos'\)->name\('finanzas\.ingresos_percibidos'\);)"
    if len(re.findall(pat, r)) != 1:
        sys.exit('ERROR: no encontre la ruta de ingresos percibidos. No se cambio nada.')
    r = re.sub(pat, lambda x: x.group(1) + "\n\n    Route::get('finanzas/reservas-del-dia', 'ReservasDelDiaController@index')->name('finanzas.reservas_dia');", r, count=1)
    shutil.copy(RUTAS, RUTAS + '.bak.' + ts)
    open(RUTAS, 'w', encoding='utf-8').write(r)
    print('Ruta agregada')
if 'finanzas.reservas_dia' not in m:
    pat = r"(<li class=\"bold\">\s*<a class=\"waves-effect waves-cyan\" href=\"\{\{ route\('backoffice\.finanzas\.utilidad'\) \}\}\">.*?</li>)"
    if len(re.findall(pat, m, re.S)) != 1:
        sys.exit('ERROR: no encontre el enlace de Utilidad en el menu (la ruta si quedo). Entra por /finanzas/reservas-del-dia')
    m = re.sub(pat, lambda x: x.group(1) + '''

                    <li class="bold">
                        <a class="waves-effect waves-cyan" href="{{ route('backoffice.finanzas.reservas_dia') }}">
                            <i class="material-icons">event_available</i>
                            <span class="nav-text">Reservas del día</span>
                        </a>
                    </li>''', m, count=1, flags=re.S)
    shutil.copy(MENU, MENU + '.bak.' + ts)
    open(MENU, 'w', encoding='utf-8').write(m)
    print('Enlace agregado al menu (debajo de Utilidad)')
