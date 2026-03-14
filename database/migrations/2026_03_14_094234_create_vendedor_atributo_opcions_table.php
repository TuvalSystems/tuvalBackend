<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendedorAtributoOpcionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendedor_atributo_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atributo_id')->constrained('vendedor_atributos')->cascadeOnDelete();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->dateTime('modified_at')->nullable();
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
        Schema::dropIfExists('vendedor_atributo_opciones');
    }
}
