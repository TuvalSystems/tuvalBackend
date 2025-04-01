<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTempCartProveedoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_temp_cart_proveedores', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_tempcart')->unsigned();
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
        Schema::table('gp_temp_cart_proveedores', function (Blueprint $table) {
            $table->foreign('id_tempcart', 'gp_temp_cart_proveedores_fk1')->references('id')->on('gp_temp_carts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_temp_cart_proveedores');
    }
}
