<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpUsuariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('USERID', 20)->unique();
            $table->string('USERNAME', 100)->nullable();
            $table->integer('NIVEL_AUTORIZA_CRED')->nullable();
            $table->bigInteger('perfil_id')->nullable()->unsigned();
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
        Schema::dropIfExists('gp_usuarios');
    }
}
