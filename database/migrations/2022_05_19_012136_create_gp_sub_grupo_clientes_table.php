<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpSubGrupoClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_sub_grupo_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('SUBGRUPO_CODIGO', 20)->unique();
            $table->string('SUBGRUPO_DESCRIPCION', 30)->nullable();
            $table->string('GRUPO_CODIGO', 20)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_sub_grupo_clientes', function (Blueprint $table) {
            $table->foreign('GRUPO_CODIGO', 'gp_sub_grupo_clientes_fk1')->references('GRUPO_CODIGO')
                    ->on('gp_grupo_clientes')->onDelete('cascade')->onUpdate('no action'); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_sub_grupo_clientes');
    }
}
