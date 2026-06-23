<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hi_cotizacion_detalle_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hi_cot_id')->nullable()->constrained('hi_cotizacion')->nullOnDelete();
            $table->foreignId('hi_cot_det_id')->nullable()->constrained('hi_cotizacion_detalles')->nullOnDelete();
            $table->unsignedBigInteger('cot_id')->nullable()->index();
            $table->unsignedBigInteger('det_id')->nullable()->index();
            $table->string('STO_ART_BODEGA', 20);
            $table->decimal('STO_ART_STOCK_DISPONIBLE', 11, 2)->nullable();
            $table->decimal('seleccionado', 11, 2)->nullable();
            $table->decimal('cantidad_autorizada_reserva', 18, 2)->nullable()->default(0);
            $table->decimal('cantidad_facturada', 18, 2)->nullable()->default(0);
            $table->unsignedDecimal('cantidad_reservada', 18, 2)
                ->default(0)
                ->comment('Cantidad reservada para cálculos de pendientes y disponibles');

            $table->decimal('cantidad_cotizada', 18, 2)->nullable()->default(0);
            $table->decimal('cantidad_traslado', 18, 2)->nullable()->default(0);
            $table->decimal('cantidad_transito', 18, 2)
                ->default(0)
                ->comment('Cantidad de transito que se almacena de forma temporal para la resta de disponible antes de que llegue a la bodega destino');
            $table->decimal('cantidad_cancelada', 18, 2)->nullable()->default(0);

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
        Schema::dropIfExists('hi_cotizacion_detalle_stocks');
    }
};
