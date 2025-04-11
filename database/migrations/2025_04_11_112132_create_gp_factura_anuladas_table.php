<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpFacturaAnuladasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_factura_anuladas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pedido_id')->unsigned();
            $table->string('pedido', 20)->nullable();
            $table->string('factura', 20)->nullable();
            $table->integer('documento_id')->nullable();
            $table->string('claveacceso', 100)->nullable();
            $table->string('msj_anulacion_gp', )->nullable();
            $table->integer('diario_response_gp', )->nullable();
            $table->string('resp_document', )->nullable();
            $table->string('resp_seedbilling', )->nullable();
            $table->enum('estado', ['pendiente', 'enviada'])->default('enviada');
            $table->timestamps();
            $table->foreign('pedido_id')->references('id')->on( 'gp_pedidos')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_factura_anuladas');
    }
}
