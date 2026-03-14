<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendedorAtributoValorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendedor_atributo_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('gp_vendedors')->cascadeOnDelete();
            $table->foreignId('atributo_id')->constrained('vendedor_atributos')->cascadeOnDelete();
            $table->foreignId('opcion_id')->nullable()->constrained('vendedor_atributo_opciones')->nullOnDelete();

            $table->string('valor_string')->nullable();
            $table->integer('valor_integer')->nullable();
            $table->decimal('valor_decimal', 18, 4)->default(0);
            $table->boolean('valor_boolean')->default(false);
            $table->date('valor_date')->nullable();
            $table->foreignId('user_creator_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('user_modificator_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
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
        Schema::dropIfExists('vendedor_atributo_valores');
    }
}
