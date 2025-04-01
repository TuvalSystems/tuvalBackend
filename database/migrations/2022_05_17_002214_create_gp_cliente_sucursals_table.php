<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpClienteSucursalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cliente_sucursals', function (Blueprint $table) {
            $table->id();
            $table->string('CLI_SUC_RUC', 15);
            $table->string('CLI_SUC_DESCRIPCION', 65)->nullable();
            $table->string('CLI_SUC_ID_DIRECCION', 15)->nullable();
            $table->string('CLI_SUC_DIRECCION', 300)->nullable();
            $table->string('CLI_SUC_CONTACTO', 100)->nullable();
            $table->string('CLI_SUC_REFERENCIA', 100)->nullable();
            $table->string('CLI_SUC_VENDEDOR', 20)->nullable();
            $table->string('CLI_SUC_TERRITORIO', 15)->nullable();
            $table->string('CLI_SUC_CIUDAD', 65)->nullable();
            $table->string('CLI_SUC_PROVINCIA', 30)->nullable();
            $table->string('CLI_SUC_PAIS', 30)->nullable();
            $table->string('CLI_SUC_TELEFONO', 21)->nullable();
            $table->string('CLI_SUC_CORREO_ELECTRONICO_SRI', 200)->nullable();
            $table->string('coordenadas', 100)->nullable();
            $table->string('CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA', 200)->nullable();
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
        Schema::dropIfExists('gp_cliente_sucursals');
    }
}
