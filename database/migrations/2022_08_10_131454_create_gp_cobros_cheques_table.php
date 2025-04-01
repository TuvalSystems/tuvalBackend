<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCobrosChequesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cobros_cheques', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cobro_id')->unsigned();
            $table->string('banco', 20);
            $table->string('cuenta', 30);
            $table->string('cheque', 30);
            $table->decimal('monto', 11, 2);
            $table->string('comentario', 200)->nullable();
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            $table->timestamps();
        });
        Schema::table('gp_cobros_cheques', function (Blueprint $table) {
            $table->foreign('cobro_id', 'gp_cobros_cheques_fk0')->references('id')->on('gp_cobros')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cobros_cheques');
    }
}
