<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpProspectosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_prospectos', function (Blueprint $table) {
            $table->id();
            $table->string('CLI_RUC', 30)->nullable();
            $table->string('CLI_RAZON_SOCIAL', 300)->nullable();
            $table->string('CLI_DIRECCION', 300)->nullable();
            $table->string('CLI_CORREO_ELECT_SRI', 300)->nullable();
            $table->string('CLI_TELEFONO_1', 21)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->foreign('prospecto_id', 'gp_cotizacions_fk11')->references('id')->on('gp_prospectos')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_prospectos');
    }
}
