# Apunta /egreso/{anio}/{mes} al panel nuevo y deja la vista anterior en /egreso/{anio}/{mes}/anterior. Idempotente.
p = 'routes/web.php'
s = open(p, encoding='utf-8').read()
viejo = "Route::get('/egreso/{anio}/{mes}', 'EgresoController@index_mes')->name('egreso.mes');"
nuevo = ("Route::get('/egreso/{anio}/{mes}/anterior', 'EgresoController@index_mes')->name('egreso.mes.anterior');\n"
         "    Route::get('/egreso/{anio}/{mes}', 'EgresosPanelController@mes')->name('egreso.mes');")
if 'EgresosPanelController@mes' in s:
    print('Panel de egresos: ya estaba')
elif viejo in s:
    s = s.replace(viejo, nuevo, 1)
    open(p, 'w', encoding='utf-8').write(s)
    print('Panel de egresos: OK')
else:
    raise SystemExit('no encontre la ruta egreso.mes')
