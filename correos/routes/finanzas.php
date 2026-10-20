<?php

/*
| Finanzas (lectura de correos bancarios y egresos). Se carga dentro del grupo
| ['middleware' => ['auth'], 'as' => 'backoffice.'] de routes/web.php.
*/
Route::prefix('finanzas/correos')->name('finanzas.correos.')->group(function () {
    Route::get('/', 'FinanzasCorreoController@index')->name('index');
    Route::get('/movimientos', 'FinanzasCorreoController@movimientos')->name('movimientos');
    Route::post('/leer', 'FinanzasCorreoController@leer')->name('leer');
    Route::get('/{id}', 'FinanzasCorreoController@show')->name('show');
    Route::post('/{id}/asociar', 'FinanzasCorreoController@asociar')->name('asociar');
    Route::post('/{id}/vincular', 'FinanzasCorreoController@vincular')->name('vincular');
    Route::post('/{id}/pagar-semana', 'FinanzasCorreoController@pagarSemana')->name('pagar_semana');
    Route::post('/{id}/estado', 'FinanzasCorreoController@estado')->name('estado');
});

// Egresos › Clasificar: bandeja de movimientos del banco por asociar, registrar o clasificar
Route::prefix('finanzas/clasificar')->name('finanzas.clasificar.')->group(function () {
    Route::get('/', 'FinanzasClasificarController@index')->name('index');
    Route::post('/{id}/venta', 'FinanzasClasificarController@venta')->name('venta');
    Route::post('/{id}/marcar', 'FinanzasClasificarController@marcar')->name('marcar');
    Route::post('/{id}/egreso', 'FinanzasClasificarController@egreso')->name('egreso');
    Route::post('/{id}/deshacer', 'FinanzasClasificarController@deshacer')->name('deshacer');
});
