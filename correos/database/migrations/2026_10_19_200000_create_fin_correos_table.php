<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Correos bancarios y de cuentas leídos automáticamente (Finanzas, fase 1). */
class CreateFinCorreosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('fin_correos')) {
            return;
        }
        Schema::create('fin_correos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('message_id', 191)->unique();
            $table->unsignedInteger('uid')->nullable();
            $table->dateTime('recibido_at')->nullable();
            $table->string('remitente', 120)->nullable();
            $table->string('asunto', 191)->nullable();
            $table->string('tipo', 12)->index();               // abono | pago | cuenta | cartola | factura
            $table->string('banco', 40)->nullable();
            $table->string('proveedor', 60)->nullable();
            $table->string('nombre', 120)->nullable();         // quien paga (abono) o a quien se paga (pago)
            $table->string('rut', 15)->nullable();
            $table->integer('monto')->nullable();
            $table->date('fecha')->nullable()->index();
            $table->string('hora', 5)->nullable();
            $table->string('operacion', 60)->nullable()->index();
            $table->string('comentario', 191)->nullable();
            $table->string('cuenta', 30)->nullable();          // cuenta de destino (abonos)
            $table->date('vencimiento')->nullable();
            $table->string('estado', 12)->default('nuevo')->index(); // nuevo | calzado | revisar | ignorado | pagado
            $table->unsignedInteger('venta_id')->nullable()->index();
            $table->string('campo', 12)->nullable();           // abono | diferencia | extra
            $table->unsignedInteger('abono_extra_id')->nullable();
            $table->unsignedBigInteger('comprobante_id')->nullable(); // comprobantes_revision
            $table->unsignedInteger('egreso_id')->nullable();
            $table->string('nota', 191)->nullable();
            $table->mediumText('texto')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fin_correos');
    }
}
