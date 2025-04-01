<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCierreCajasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_cierre_cajas', function (Blueprint $table) {
            $table->id();
            $table->string('CAJA_CODIGO', 15);
            $table->date('fecha');
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->dateTime('fecha_cierre')->nullable();
            $table->dateTime('fecha_apertura')->nullable();
            $table->dateTime('fecha_reapertura')->nullable();
            $table->mediumText('datos')->nullable();
            $table->mediumText('consulta')->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cierre_cajas', function (Blueprint $table) {
            $table->foreign('CAJA_CODIGO', 'gp_cierre_cajasfk0')->references('CAJA_CODIGO')->on('gp_cajas')->onDelete('no action');
            $table->unique(['CAJA_CODIGO', 'fecha'],'caja_fecha');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('gp_cierre_cajas');
    }
}
