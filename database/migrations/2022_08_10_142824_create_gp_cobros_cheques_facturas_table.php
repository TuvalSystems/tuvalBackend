<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCobrosChequesFacturasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cobros_cheques_facturas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cobro_id')->unsigned();
            $table->decimal('monto', 11, 2);
            $table->string('id_factura', 30);
            $table->string('observacion', 200)->nullable();
            $table->date('ECD_DET_FECHA_VENCIMIENTO')->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cobros_cheques_facturas', function (Blueprint $table) {
            $table->foreign('cobro_id', 'gp_cobros_cheques_facturas_fk0')->references('id')->on('gp_cobros')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cobros_cheques_facturas');
    }
}
