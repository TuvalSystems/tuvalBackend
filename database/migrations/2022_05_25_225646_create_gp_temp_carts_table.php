<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTempCartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_temp_carts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('detalle_id')->unsigned()->nullable();
            $table->bigInteger('sucursal_id')->unsigned();
            $table->bigInteger('cliente_id')->unsigned();
            $table->string('puntodeventa_id',10);
            $table->bigInteger('user_id')->unsigned();
            $table->string('USERID', 20);
            $table->string('ART_CODIGO', 30)->nullable();
            $table->string('ART_DESCRIPCION', 300)->nullable();
            $table->string('ART_DESCRIPCION_ORIGINAL', 300)->nullable();
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
            $table->decimal('ART_DESCUENTO', 11, 4)->nullable();
            $table->decimal('ART_CANTIDAD', 11, 2)->nullable();
            $table->decimal('ART_STOCK', 11, 2)->nullable();
            $table->decimal('ART_IMP_VTA', 11, 2)->nullable();
            $table->decimal('ART_COSTO_PROMEDIO', 11, 2)->nullable();
            $table->decimal('descuento', 11, 2)->nullable();
            $table->decimal('descuento_original', 11, 2)->nullable();
            $table->string('modificador', 30)->nullable();
            $table->integer('orden')->nullable();
            $table->timestamps();
        });
        Schema::table('gp_temp_carts', function (Blueprint $table) {
            $table->foreign('sucursal_id', 'gp_temp_carts_fk1')->references('id')->on('gp_cliente_sucursals')->onDelete('no action');
            $table->foreign('cliente_id', 'gp_temp_carts_fk2')->references('id')->on('gp_clientes')->onDelete('no action');
            $table->foreign('puntodeventa_id', 'gp_temp_carts_fk3')->references('PTO_VTA_ID')->on('gp_punto_ventas')->onDelete('no action');
            $table->foreign('user_id', 'gp_temp_carts_fk4')->references('id')->on('gp_usuarios')->onDelete('no action');
            $table->foreign('detalle_id', 'gp_temp_carts_fk5')->references('id')->on('gp_cotizacion_detalles')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_temp_carts');
    }
}
