import re, sys, shutil, time, os
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
RUTAS = B + 'routes/web.php'
INICIO = B + 'resources/views/themes/backoffice/pages/admin/show.blade.php'
ts = time.strftime('%Y%m%d%H%M%S')
r = open(RUTAS, encoding='utf-8').read()
if 'bot-conversaciones.index' not in r:
    pat = r"(Route::get\('finanzas/ingresos',\s*'ReporteFinancieroController@ingresosPercibidos'\)->name\('finanzas\.ingresos_percibidos'\);)"
    if len(re.findall(pat, r)) != 1:
        sys.exit('ERROR: no encontre donde agregar la ruta. No se cambio nada.')
    r = re.sub(pat, lambda x: x.group(1) + "\n\n    Route::get('bot-conversaciones', 'BotConversacionesController@index')->name('bot-conversaciones.index');", r, count=1)
    shutil.copy(RUTAS, RUTAS + '.bak.' + ts)
    open(RUTAS, 'w', encoding='utf-8').write(r)
    print('Ruta agregada: /bot-conversaciones')
t = open(INICIO, encoding='utf-8').read()
viejo = "{{ route('backoffice.bot-configuracion.index') }}#aprendizajes"
if viejo in t:
    t = t.replace(viejo, "{{ route('backoffice.bot-conversaciones.index') }}")
    shutil.copy(INICIO, INICIO + '.bak.' + ts)
    open(INICIO, 'w', encoding='utf-8').write(t)
    print('Tarjeta "Conversaciones del bot" apunta a la pagina nueva')
elif 'bot-conversaciones.index' in t:
    print('La tarjeta ya apuntaba a la pagina nueva')
else:
    print('AVISO: no encontre la tarjeta "Conversaciones del bot" en el inicio')
