<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTipoReservasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_tipo_reservas', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 100);
            // $table->unsignedTinyInteger('dias_max_reserva')->nullable()->comment('Cantidad máxima de días para la reserva');
            $table->boolean('estado')->default(1);
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
        Schema::dropIfExists('gp_tipo_reservas');
    }
}
