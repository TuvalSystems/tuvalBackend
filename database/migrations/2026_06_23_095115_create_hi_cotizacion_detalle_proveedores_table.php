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
        Schema::create('hi_cotizacion_detalle_proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hi_cot_id')->nullable()->constrained('hi_cotizacion')->nullOnDelete();
            $table->foreignId('hi_cot_det_id')->nullable()->constrained('hi_cotizacion_detalles')->nullOnDelete();
            $table->unsignedBigInteger('cot_id')->nullable()->index();
            $table->unsignedBigInteger('det_id')->nullable()->index();
            $table->bigInteger('ID_PROVEEDOR');
            $table->string('DESCRIPCION_ARTICULO', 300);
            $table->date('FECHA_ENTREGA');
            $table->decimal('CANTIDAD', 11, 2);
            $table->decimal('COSTO_UNITARIO', 11, 2);
            $table->decimal('IMPUESTO', 11, 2);
            $table->decimal('TOTAL', 11, 2);
            $table->decimal('TOTALCONIMPUESTO', 11, 2);
            $table->decimal('MARGEN', 11, 2);
            $table->string('NOTA', 300)->nullable();
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
        Schema::dropIfExists('hi_cotizacion_detalle_proveedores');
    }
};
