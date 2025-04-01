<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCobrosRetencionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cobros_retenciones', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cobro_id')->unsigned();
            $table->string('ret_factura', 30);
            $table->date('ret_fecha_emision');
            $table->date('ret_fecha_vencimiento');
            $table->decimal('ret_subtotal_factura', 11, 2);
            $table->decimal('ret_iva_factura', 11, 2);
            $table->decimal('ret_total_factura', 11, 2);
            $table->decimal('ret_saldo_factura', 11, 2);
            $table->decimal('ret_iva', 11, 2);
            $table->decimal('ret_porcentaje_iva', 11, 2);
            $table->decimal('ret_renta', 11, 2);
            $table->decimal('ret_porcentaje_renta', 11, 2);

            $table->decimal('ret_iva_srv', 11, 2)->nullable();
            $table->decimal('ret_porcentaje_iva_srv', 11, 2)->nullable();
            $table->decimal('ret_renta_srv', 11, 2)->nullable();
            $table->decimal('ret_porcentaje_renta_srv', 11, 2)->nullable();

            $table->date('ret_emision');
            $table->date('ret_caducidad')->nullable();
            $table->string('ret_comprobante', 30);
            $table->string('ret_autorizacion', 50);
            $table->decimal('total', 11, 2);
            $table->string('codigo_ws_iva', 30)->nullable();
            $table->string('mensaje_ws_iva', 300)->nullable();
            $table->string('codigo_ws_renta', 30)->nullable();
            $table->string('mensaje_ws_renta', 300)->nullable();

            $table->string('codigo_ws_iva_srv', 30)->nullable();
            $table->string('mensaje_ws_iva_srv', 300)->nullable();
            $table->string('codigo_ws_renta_srv', 30)->nullable();
            $table->string('mensaje_ws_renta_srv', 300)->nullable();

            $table->timestamps();
        });
        Schema::table('gp_cobros_retenciones', function (Blueprint $table) {
            $table->foreign('cobro_id', 'gp_cobros_retenciones_fk0')->references('id')->on('gp_cobros')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cobros_retenciones');
    }
}
