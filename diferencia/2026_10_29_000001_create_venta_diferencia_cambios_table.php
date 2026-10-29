<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVentaDiferenciaCambiosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('venta_diferencia_cambios')) {
            return;
        }
        Schema::create('venta_diferencia_cambios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_venta')->index();
            $table->unsignedBigInteger('id_reserva')->nullable()->index();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->integer('monto_anterior')->nullable();
            $table->integer('monto_nuevo');
            $table->unsignedBigInteger('id_tipo_anterior')->nullable();
            $table->unsignedBigInteger('id_tipo_nuevo')->nullable();
            $table->string('campo', 30)->default('diferencia_programa');
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('venta_diferencia_cambios');
    }
}
