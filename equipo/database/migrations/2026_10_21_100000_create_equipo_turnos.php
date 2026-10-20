<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos sugeridos según la cantidad de clientes:
 *  - equipo_config: área (atención, cocina, fijo, apoyo), días que puede trabajar, prioridad y teléfono de cada persona.
 *  - equipo_parametros: reglas (clientes por persona de atención, mínimo, umbral del copero, teléfonos del aviso).
 *  - turno_propuestas: la propuesta de cada día, cuándo se avisó y si se aceptó (pasa a Asignación de equipo).
 */
class CreateEquipoTurnos extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('equipo_config')) {
            Schema::create('equipo_config', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('user_id')->unique();
                $t->string('area', 20)->default('atencion');      // atencion | cocina | fijo | apoyo | no
                $t->string('dias', 20)->default('4,5,6,7');        // 1 = lunes … 7 = domingo
                $t->unsignedTinyInteger('prioridad')->default(5);  // 1 = primero en entrar
                $t->string('telefono', 20)->nullable();            // para avisarle directo (más adelante)
                $t->string('nota', 191)->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('equipo_parametros')) {
            Schema::create('equipo_parametros', function (Blueprint $t) {
                $t->string('clave', 60)->primary();
                $t->string('valor', 191)->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('turno_propuestas')) {
            Schema::create('turno_propuestas', function (Blueprint $t) {
                $t->increments('id');
                $t->date('fecha')->unique();
                $t->unsignedInteger('personas')->default(0);
                $t->unsignedInteger('reservas')->default(0);
                $t->text('propuesta');                              // JSON
                $t->text('mensaje')->nullable();
                $t->timestamp('avisado_at')->nullable();
                $t->string('aviso_estado', 191)->nullable();
                $t->timestamp('aceptada_at')->nullable();
                $t->unsignedInteger('aceptada_por')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('turno_propuestas');
        Schema::dropIfExists('equipo_parametros');
        Schema::dropIfExists('equipo_config');
    }
}
