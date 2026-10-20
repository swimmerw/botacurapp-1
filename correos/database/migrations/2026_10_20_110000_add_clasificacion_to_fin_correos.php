<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clasificación manual de movimientos (bandeja "Clasificar" del módulo de egresos). */
class AddClasificacionToFinCorreos extends Migration
{
    public function up()
    {
        Schema::table('fin_correos', function (Blueprint $table) {
            if (!Schema::hasColumn('fin_correos', 'clasificacion')) {
                $table->string('clasificacion', 30)->nullable()->after('vinculo');
            }
            if (!Schema::hasColumn('fin_correos', 'categoria_id')) {
                $table->unsignedInteger('categoria_id')->nullable()->after('clasificacion');
            }
            if (!Schema::hasColumn('fin_correos', 'subcategoria_id')) {
                $table->unsignedInteger('subcategoria_id')->nullable()->after('categoria_id');
            }
            if (!Schema::hasColumn('fin_correos', 'clasificado_por')) {
                $table->unsignedInteger('clasificado_por')->nullable()->after('subcategoria_id');
            }
            if (!Schema::hasColumn('fin_correos', 'clasificado_at')) {
                $table->timestamp('clasificado_at')->nullable()->after('clasificado_por');
            }
        });
    }

    public function down()
    {
        Schema::table('fin_correos', function (Blueprint $table) {
            foreach (['clasificacion', 'categoria_id', 'subcategoria_id', 'clasificado_por', 'clasificado_at'] as $c) {
                if (Schema::hasColumn('fin_correos', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
