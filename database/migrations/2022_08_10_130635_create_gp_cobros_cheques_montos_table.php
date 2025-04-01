<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCobrosChequesMontosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cobros_cheques_montos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cobro_id')->unsigned();
            $table->decimal('monto', 11, 2);
            $table->decimal('saldo', 11, 2);
            $table->string('codigo_ws', 20)->nullable();
            $table->string('mensaje_ws', 300)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cobros_cheques_montos', function (Blueprint $table) {
            $table->foreign('cobro_id', 'gp_cobros_cheques_montos_fk0')->references('id')->on('gp_cobros')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cobros_cheques_montos');
    }
}
