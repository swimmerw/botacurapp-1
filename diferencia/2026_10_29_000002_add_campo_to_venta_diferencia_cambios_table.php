<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCampoToVentaDiferenciaCambiosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('venta_diferencia_cambios') && !Schema::hasColumn('venta_diferencia_cambios', 'campo')) {
            Schema::table('venta_diferencia_cambios', function (Blueprint $table) {
                $table->string('campo', 30)->default('diferencia_programa')->after('id_tipo_nuevo');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('venta_diferencia_cambios', 'campo')) {
            Schema::table('venta_diferencia_cambios', function (Blueprint $table) {
                $table->dropColumn('campo');
            });
        }
    }
}
