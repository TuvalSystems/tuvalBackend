<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCotizacionDetalleProveedoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cotizacion_detalle_proveedores', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cotizacion_id')->unsigned();
            $table->bigInteger('detalle_id')->unsigned();
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
        Schema::table('gp_cotizacion_detalle_proveedores', function (Blueprint $table) {
            $table->foreign('cotizacion_id', 'gp_cotizacion_detalle_proveedores_fk1')->references('id')->on('gp_cotizacions')->onDelete('cascade');
            $table->foreign('detalle_id', 'gp_cotizacion_detalle_proveedores_fk2')->references('id')->on('gp_cotizacion_detalles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cotizacion_detalle_proveedores');
    }
}
