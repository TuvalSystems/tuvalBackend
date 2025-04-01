<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('CLI_RUC', 30)->unique();
            $table->string('CLI_RAZON_SOCIAL', 300)->nullable();
            $table->string('CLI_NOMBRE_CORTO', 30)->nullable();
            $table->string('CLI_CLASE', 30)->nullable();
            $table->integer('CLI_PRIORIDAD')->nullable();
            $table->string('CLI_ID_DIRECCION', 30)->nullable();
            $table->string('CLI_CONTACTO', 60)->nullable();
            $table->string('CLI_DIRECCION', 122)->nullable();
            $table->string('CLI_CIUDAD', 35)->nullable();
            $table->string('CLI_PROVINCIA', 30)->nullable();
            $table->string('CLI_TERRITORIO', 15)->nullable();
            $table->string('CLI_ESTADO', 10)->nullable();
            $table->string('CLI_SUSPENDIDO', 10)->nullable();
            $table->string('CLI_TELEFONO_1', 21)->nullable();
            $table->string('CLI_TELEFONO_2', 21)->nullable();
            $table->string('CLI_TELEFONO_3', 21)->nullable();
            $table->string('CLI_TELEFONO_4', 21)->nullable();
            $table->dateTime('CLI_FECHA_MODIFICACION');
            $table->dateTime('CLI_FECHA_CREACION');
            $table->string('CLI_CORREO_ELECT_SRI', 200)->nullable();
            $table->string('CLI_CORREO_ELECT_NOTIFICACION_INTERNA', 200)->nullable();
            $table->decimal('CLI_IMPUESTO', 11, 2)->nullable();
            $table->string('CLI_DIRECCION_ENVIO', 15)->nullable();
            $table->string('CLI_DIRECCION_FACTURA', 15)->nullable();
            $table->string('CLI_VENDEDOR', 15)->nullable();
            $table->decimal('CLI_DSTO_COMERCIAL', 11, 2)->nullable();
            $table->string('CLI_CONDICION_PAGO', 21)->nullable();
            $table->integer('CLI_DIA_GRACIA_VTA_CREDITO')->nullable();
            $table->integer('CLI_DIA_GRACIA_CHEQUE_A_FECHA')->nullable();
            $table->string('CLI_NIVEL_PRECIO', 11)->nullable();
            $table->string('CLI_TIPO_CREDITO', 20)->nullable();
            $table->decimal('CLI_MONTO_CREDITO', 11, 2)->nullable();
            $table->string('CLI_GRUPO_ECONOMICO', 30)->nullable();
            $table->string('CLI_GRUPO', 30)->nullable();
            $table->string('CLI_SUBGRUPO', 30)->nullable();
            $table->integer('CLI_DIA_MAXIMO_MES_FACTURACION')->nullable();
            $table->string('CLI_FACTURA_POSTERIOR_AL_DESPACHO', 5)->nullable();
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
        Schema::dropIfExists('gp_clientes');
    }
}
