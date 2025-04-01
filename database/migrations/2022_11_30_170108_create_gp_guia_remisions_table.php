<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpGuiaRemisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_guia_remisions', function (Blueprint $table) {
            $table->id();
            $table->string('GUI_REM_CODIGO', 100)->nullable();
            $table->enum('tipo', ['transferencia', 'reposicion', 'despacho'])->default('despacho');
            $table->enum('estado', ['pendiente', 'enviada', 'recibida', 'eliminada'])->default('pendiente');
            $table->string('bodega_origen', 20);
            $table->string('bodega_destino', 20)->nullable();
            $table->string('pedido', 20)->nullable();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('GUI_REM_BODEGA', 50);
            $table->string('GUI_REM_GUIA_REMISION', 20)->unique();
            $table->string('GUI_REM_ESTABLECIMIENTO', 3);
            $table->string('GUI_REM_PTO_EMISION', 3);
            $table->string('GUI_REM_SECUENCIAL', 9);
            $table->string('GUI_REM_DIRECCION_PARTIDA', 200);
            $table->string('GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 300);
            $table->string('GUI_REM_IDENTIFICACION_TRANSPORTISTA', 20);
            $table->enum('GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA', ['04', '05', '06'])->default('04');
            $table->dateTime('GUI_REM_FECHA_INICIO');
            $table->dateTime('GUI_REM_FECHA_FIN');
            $table->string('GUI_REM_PLACA', 10);
            $table->string('GUI_REM_OBSERVACION', 300)->nullable();
            $table->string('GUI_REM_DIRECCION_DESTINATARIO', 200);
            $table->string('GUI_REM_RAZON_SOCIAL_DESTINATARIO', 200);
            $table->string('GUI_REM_IDENTIFICACION_DESTINATARIO', 20);
            $table->enum('GUI_REM_MOTIVO_TRASLADO', ['Venta', 'Transferencia', 'Devolución'])->default('Venta');
            $table->string('GUI_REM_RUTA', 100)->nullable();
            $table->string('GUI_REM_CODI_DOCU_SUSTENTO', 30)->nullable();
            $table->string('GUI_REM_NUME_DOCU_SUSTENTO', 20)->nullable();
            $table->string('GUI_REM_NUME_AUTO_DOCU_SUSTENTO', 20)->nullable();
            $table->dateTime('GUI_REM_FECHA_EMISION_DOCU_SUSTENTO')->nullable();
            $table->boolean('rebajado')->nullable();
            $table->integer('recibidos')->nullable();
            $table->boolean('procesando')->nullable();
            $table->timestamps();
        });
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->foreign('user_id', 'gp_guia_remisions_fk0')->references('id')->on('gp_usuarios')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('gp_guia_remisions');
    }
}
