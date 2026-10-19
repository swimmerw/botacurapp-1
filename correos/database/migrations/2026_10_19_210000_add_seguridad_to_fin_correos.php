<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Resultado de la verificación de autenticidad (firma DKIM del banco) de cada correo. */
class AddSeguridadToFinCorreos extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('fin_correos', 'seguridad')) {
            Schema::table('fin_correos', function (Blueprint $table) {
                $table->string('seguridad', 12)->nullable()->after('estado'); // seguro | revisar | falso
                $table->string('seguridad_detalle', 191)->nullable()->after('seguridad');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('fin_correos', 'seguridad')) {
            Schema::table('fin_correos', function (Blueprint $table) {
                $table->dropColumn(['seguridad', 'seguridad_detalle']);
            });
        }
    }
}
