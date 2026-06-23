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
        Schema::create('hi_cotizacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hi_cot_id')->nullable()->constrained('hi_cotizacion')->cascadeOnDelete();
            $table->unsignedBigInteger('cot_id')->nullable()->index();
            $table->unsignedBigInteger('det_id')->nullable()->index();
            $table->string('ART_CODIGO', 30)->nullable();
            $table->string('ART_DESCRIPCION', 300)->nullable();
            $table->string('ART_DESCRIPCION_ORIGINAL', 300)->nullable();
            $table->boolean('CHECK_ALTERNATIVO')->default(0);
            $table->string('ART_CODIGO_TRES_LETRAS', 3)->nullable();
            $table->integer('ART_DECIMAL_CANTIDAD')->nullable();
            $table->string('ART_PLAN_UMEDIDA', 30)->nullable();
            $table->string('ART_MARCA', 300)->nullable();
            $table->string('ART_MATERIAL', 300)->nullable();
            $table->decimal('ART_PESO', 11, 2)->nullable();
            $table->string('ART_TIPO_COMPRA', 5)->nullable();
            $table->decimal('ART_VALOR', 11, 4)->nullable();
            $table->decimal('ART_IVA', 11, 2)->nullable();
            $table->decimal('ART_VALOR_ORIGINAL', 11, 4)->nullable();
            $table->decimal('valor_original_l', 11, 4)->default(0)->nullable();
            $table->decimal('ART_DESCUENTO', 11, 4)->nullable();
            $table->decimal('ART_CANTIDAD', 11, 2)->nullable();
            $table->decimal('ART_STOCK', 11, 2)->nullable();
            $table->decimal('ART_IMP_VTA', 11, 2)->nullable();
            $table->decimal('ART_COSTO_PROMEDIO', 11, 2)->nullable();
            $table->decimal('descuento', 11, 2)->nullable();
            $table->decimal('descuento_original', 11, 2)->nullable();
            $table->string('modificador', 30)->nullable();

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
        Schema::dropIfExists('hi_cotizacion_detalles');
    }
};
