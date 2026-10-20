<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vínculos de las salidas: sueldo pagado, pago de egreso, cuenta pagada (correo con correo). */
class AddVinculosToFinCorreos extends Migration
{
    public function up()
    {
        Schema::table('fin_correos', function (Blueprint $table) {
            if (!Schema::hasColumn('fin_correos', 'vinculo')) {
                $table->string('vinculo', 20)->nullable()->after('estado');
            }
            if (!Schema::hasColumn('fin_correos', 'sueldo_pagado_id')) {
                $table->unsignedInteger('sueldo_pagado_id')->nullable()->after('egreso_id');
            }
            if (!Schema::hasColumn('fin_correos', 'pago_egreso_id')) {
                $table->unsignedInteger('pago_egreso_id')->nullable()->after('egreso_id');
            }
            if (!Schema::hasColumn('fin_correos', 'correo_id')) {
                $table->unsignedInteger('correo_id')->nullable()->after('egreso_id');
            }
        });
    }

    public function down()
    {
        Schema::table('fin_correos', function (Blueprint $table) {
            foreach (['vinculo', 'sueldo_pagado_id', 'pago_egreso_id', 'correo_id'] as $c) {
                if (Schema::hasColumn('fin_correos', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
