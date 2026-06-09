<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCobroValidacionFacturaDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cobro_validacion_factura_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('validacion_factura_id')
                ->constrained('cobro_validacion_facturas')
                ->cascadeOnDelete();

            $table->string('tipo_origen', 30)->index();
            $table->string('tipo_pago', 30)->nullable()->index();
            $table->integer('codigo_tipo_pago')->nullable();

            $table->string('documento', 50)->nullable()->index();
            $table->string('numero_recibo', 50)->nullable()->index();

            $table->decimal('monto', 18, 2)->default(0);

            $table->date('fecha_documento')->nullable();
            $table->date('fecha_aplicacion')->nullable();

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
        Schema::dropIfExists('cobro_validacion_factura_detalles');
    }
}
