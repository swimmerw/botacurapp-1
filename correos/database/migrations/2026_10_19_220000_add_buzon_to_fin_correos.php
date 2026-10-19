<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** De qué buzón se leyó cada correo (Botacura o personal de Sebastián). */
class AddBuzonToFinCorreos extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('fin_correos', 'buzon')) {
            Schema::table('fin_correos', function (Blueprint $table) {
                $table->string('buzon', 60)->nullable()->after('uid');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('fin_correos', 'buzon')) {
            Schema::table('fin_correos', function (Blueprint $table) {
                $table->dropColumn('buzon');
            });
        }
    }
}
