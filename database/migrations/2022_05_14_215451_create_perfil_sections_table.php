<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePerfilSectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('perfil_sections', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('section_id')->nullable()->unsigned();
            $table->bigInteger('perfil_id')->nullable()->unsigned();
            $table->enum('home', ['yes', 'no'])->default('no');
            $table->timestamps();
        });
        Schema::table('perfil_sections', function (Blueprint $table) {
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
            $table->foreign('perfil_id')->references('id')->on('perfiles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('perfil_sections');
    }
}
