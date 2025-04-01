<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpProveedorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_proveedors', function (Blueprint $table) {
            $table->id();
            $table->string('PROV_RUC', 30);
            $table->string('PROV_RAZON_SOCIAL', 100)->nullable();
            $table->string('PROV_CLASE', 15)->nullable();
            $table->string('PROV_ID_DIRECCION', 20)->nullable();
            $table->string('PROV_CONTACTO', 61)->nullable();
            $table->string('PROV_DIRECCION_1', 65)->nullable();
            $table->string('PROV_DIRECCION_2', 65)->nullable();
            $table->string('PROV_DIRECCION_3', 65)->nullable();
            $table->string('PROV_CIUDAD', 35)->nullable();
            $table->string('PROV_PROVINCIA', 29)->nullable();
            $table->string('PROV_PAIS', 61)->nullable();
            $table->string('PROV_TELEFONO_1', 21)->nullable();
            $table->string('PROV_TELEFONO_2', 21)->nullable();
            $table->string('PROV_TELEFONO_3', 21)->nullable();
            $table->string('PROV_TELEFONO_4', 21)->nullable();
            $table->string('PROV_CONDICION_PAGO', 30)->nullable();
            $table->decimal('PROV_DSTO_COMERCIAL', 11, 2)->nullable();
            $table->dateTime('PROV_FECHA_MODIFICACION');
            $table->dateTime('PROV_FECHA_CREACION');
            $table->string('PROV_CORREO_ELECT', 300)->nullable();
            $table->decimal('PROV_IMPUESTO', 11, 2)->nullable();
            $table->string('PROV_ESTADO', 10)->nullable();
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
        Schema::dropIfExists('gp_proveedors');
    }
}
