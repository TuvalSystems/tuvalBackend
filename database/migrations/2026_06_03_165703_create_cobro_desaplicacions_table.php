<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCobroDesaplicacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cobro_desaplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobro_id')->nullable()->constrained('gp_cobros')->nullOnDelete();
            $table->string('numero_recibo', 100)->nullable();
            $table->string('tipo_medio', 100)->nullable();
            $table->string('documento_desaplicar', 100)->nullable();
            $table->string('factura', 100)->nullable();
            $table->decimal('monto_cobro', 10, 2)->default(0);
            $table->decimal('monto_desaplicar', 10, 2)->default(0);
            $table->foreignId('user_aplicacion_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->dateTime('fecha_aplicacion')->nullable();
            $table->foreignId('user_desaplicacion_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->dateTime('fecha_desaplicacion')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cobro_desaplicacions');
    }
}
