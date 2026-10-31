<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Postres del día que prepara cocina (nombre y cantidad por fecha). */
class CreatePostresDiaTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('postres_dia')) {
            return;
        }
        Schema::create('postres_dia', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->date('fecha')->index();
            $t->string('nombre', 120);
            $t->unsignedInteger('cantidad')->default(0);
            $t->unsignedBigInteger('id_user')->nullable();
            $t->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('postres_dia');
    }
}
