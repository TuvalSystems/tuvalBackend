<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpArticulosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_articulos', function (Blueprint $table) {
            $table->id();
            $table->string('ART_CODIGO', 30)->unique();
            $table->string('ART_DESCRIPCION', 300)->nullable();
            $table->string('ART_CODIGO_ZAFIRO', 60)->nullable();
            $table->string('ART_CODIGO_TRES_LETRAS', 3)->nullable();
            $table->string('ART_CLASE_ARTICULO', 30);
            $table->string('ART_METODO_VALUACION', 30)->nullable();
            $table->string('ART_TIPO_PRODUCTO', 30);
            $table->dateTime('ART_FECHA_MODIFICACION');
            $table->dateTime('ART_FECHA_CREACION');
            $table->string('ART_ESTADO', 30);
            $table->decimal('ART_IMP_VTA', 11,2);
            $table->integer('ART_DECIMAL_CANTIDAD');
            $table->integer('ART_DECIMAL_MONEDA');
            $table->decimal('ART_COSTO_PROMEDIO', 11,2);
            $table->string('ART_PLAN_UMEDIDA', 30);
            $table->decimal('ART_IMP_COMPRA', 11,2);
            $table->string('ART_SUSTITUTO_1', 31)->nullable();
            $table->string('ART_SUSTITUTO_2', 31)->nullable();
            $table->string('ART_MARCA', 300)->nullable();
            $table->string('ART_MATERIAL', 300)->nullable();
            $table->string('ART_REFERENCIA_GENERAL', 300)->nullable();
            $table->string('ART_REFERENCIA_DETALLADA', 300)->nullable();
            $table->string('ART_TIPO_COMPRA', 5);
            $table->decimal('ART_PESO', 11, 5)->nullable();
            $table->string('ART_TAMANO', 30)->nullable();
            $table->string('ART_CODIGO_MARCA_MATERIAL', 630)->nullable();
            $table->string('ART_DESCRIPCION_MARCA_MATERIAL', 900)->nullable();
            $table->string('combinado', 600)->nullable();
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
        Schema::dropIfExists('gp_articulos');
    }
}
