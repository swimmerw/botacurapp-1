<?php

/* Equipo › Turnos sugeridos. Se carga dentro del grupo ['middleware' => ['auth'], 'as' => 'backoffice.'] de routes/web.php. */
Route::prefix('equipo/turnos')->name('equipo.turnos')->group(function () {
    Route::get('/', 'EquipoTurnosController@index')->name('');
    Route::post('/equipo', 'EquipoTurnosController@guardarEquipo')->name('.equipo');
    Route::post('/reglas', 'EquipoTurnosController@guardarReglas')->name('.reglas');
    Route::post('/avisar', 'EquipoTurnosController@avisar')->name('.avisar');
    Route::post('/{fecha}/aceptar', 'EquipoTurnosController@aceptar')->name('.aceptar');
});
