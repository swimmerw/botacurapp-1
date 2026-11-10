import sys, shutil, time
B = '/var/www/html/botacurapp/'
ts = time.strftime('%Y%m%d%H%M%S')
RUTAS = B + 'routes/web.php'
r = open(RUTAS, encoding='utf-8').read()
if 'reservas-en-vivo.index' not in r:
    ancla = "Route::get('bot-conversaciones', 'BotConversacionesController@index')->name('bot-conversaciones.index');"
    if r.count(ancla) != 1:
        sys.exit('ERROR: no encontre donde agregar la ruta. No se cambio nada.')
    r = r.replace(ancla, ancla + """
    Route::get('reservas-en-vivo', 'ReservasEnVivoController@index')->name('reservas-en-vivo.index');
    Route::get('reservas-en-vivo/comprobante/{venta}', 'ReservasEnVivoController@comprobante')->name('reservas-en-vivo.comprobante');""")
    shutil.copy(RUTAS, RUTAS + '.bak.' + ts); open(RUTAS, 'w', encoding='utf-8').write(r)
    print('Rutas agregadas')
print('INSTALADO')
