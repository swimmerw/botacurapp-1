<?php
// Productos Poro Poro editables desde la vista de ventas
Route::post('poroporo-productos', 'PoroProductosController@store')->name('poroporo_productos.store');
Route::patch('poroporo-productos/{id}', 'PoroProductosController@update')->name('poroporo_productos.update');
Route::delete('poroporo-productos/{id}', 'PoroProductosController@destroy')->name('poroporo_productos.destroy');
