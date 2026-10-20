<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de egresos (oct 2026):
 *  - fin_deudas / fin_deuda_pagos: todo lo que hay por pagar (cuentas que llegan al correo, deudas, créditos)
 *    y cada abono que se le hace, venga del aviso del banco o a mano.
 *  - Categorías nuevas (config/egresos.php) en categorias_compras / subcategorias_compras.
 *    Las antiguas no se borran.
 */
class CreateFinDeudasYCategorias extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('fin_deudas')) {
            Schema::create('fin_deudas', function (Blueprint $t) {
                $t->increments('id');
                $t->string('tipo', 20)->default('deuda');          // cuenta | deuda | credito
                $t->string('nombre', 150);
                $t->string('acreedor', 150)->nullable();
                $t->string('rut', 20)->nullable();
                $t->string('patron', 191)->nullable();             // palabras para reconocer los pagos en el banco
                $t->string('ambito', 20)->default('botacura');      // botacura | personal
                $t->unsignedInteger('categoria_id')->nullable();
                $t->unsignedInteger('subcategoria_id')->nullable();
                $t->bigInteger('monto_total')->default(0);
                $t->bigInteger('cuota')->nullable();
                $t->unsignedInteger('cuotas_total')->nullable();
                $t->date('vencimiento')->nullable();
                $t->unsignedInteger('correo_id')->nullable();       // la cuenta que llegó al correo
                $t->unsignedInteger('egreso_id')->nullable();
                $t->string('estado', 20)->default('pendiente');     // pendiente | pagada | anulada
                $t->string('nota', 191)->nullable();
                $t->timestamps();
                $t->index(['estado', 'tipo']);
            });
        }
        if (!Schema::hasTable('fin_deuda_pagos')) {
            Schema::create('fin_deuda_pagos', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('deuda_id');
                $t->bigInteger('monto');
                $t->date('fecha');
                $t->string('medio', 40)->nullable();                // banco | efectivo | otra cuenta | ajuste
                $t->unsignedInteger('correo_id')->nullable();       // aviso del banco que lo respalda
                $t->unsignedInteger('user_id')->nullable();
                $t->string('nota', 191)->nullable();
                $t->timestamps();
                $t->index('deuda_id');
            });
        }

        // Categorías nuevas
        if (Schema::hasTable('categorias_compras') && Schema::hasTable('subcategorias_compras')) {
            foreach ((array) config('egresos.arbol', []) as $cat => $subs) {
                $catId = DB::table('categorias_compras')->where('nombre', $cat)->value('id')
                    ?: DB::table('categorias_compras')->insertGetId(['nombre' => $cat, 'created_at' => now(), 'updated_at' => now()]);
                foreach (array_keys($subs) as $sub) {
                    if (!DB::table('subcategorias_compras')->where('categoria_id', $catId)->where('nombre', $sub)->exists()) {
                        DB::table('subcategorias_compras')->insert(['nombre' => $sub, 'categoria_id' => $catId, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('fin_deuda_pagos');
        Schema::dropIfExists('fin_deudas');
    }
}
