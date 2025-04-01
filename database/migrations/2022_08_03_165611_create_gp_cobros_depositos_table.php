<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCobrosDepositosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cobros_depositos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cobro_id')->unsigned();
            $table->enum('estado', ['iniciado', 'revisado', 'enviado'])->default('iniciado');
            $table->string('tipo', 5)->nullable();
            $table->string('banco', 20)->nullable();
            $table->string('comprobante', 30)->nullable();
            $table->date('fecha')->nullable();
            $table->decimal('monto', 11, 2);
            $table->string('codigo_ws', 20)->nullable();
            $table->string('mensaje_ws', 300)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            $table->foreign('cobro_id', 'gp_cobros_depositos_fk0')->references('id')->on('gp_cobros')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cobros_depositos');
    }
}
