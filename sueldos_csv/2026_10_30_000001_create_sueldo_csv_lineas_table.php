<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Líneas de cada CSV de sueldos descargado para el banco (para calzar el aviso de la transferencia). */
class CreateSueldoCsvLineasTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sueldo_csv_lineas')) {
            return;
        }
        Schema::create('sueldo_csv_lineas', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('lote', 40)->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->date('semana_inicio');
            $t->date('semana_fin');
            $t->integer('monto');
            $t->boolean('reemplazada')->default(false);
            $t->unsignedBigInteger('id_user_exporta')->nullable();
            $t->timestamp('pagado_at')->nullable();
            $t->unsignedBigInteger('sueldo_pagado_id')->nullable();
            $t->unsignedBigInteger('fin_correo_id')->nullable();
            $t->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sueldo_csv_lineas');
    }
}
