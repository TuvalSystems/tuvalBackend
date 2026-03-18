<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendedorJerarquiasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendedor_jerarquias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('gp_vendedors')->cascadeOnDelete();
            $table->string('nombre')->nullable();
            $table->string('descripcion')->nullable();
            $table->integer('nivel')->default(0);
            $table->boolean('estado')->default(1);
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
        Schema::dropIfExists('vendedor_jerarquias');
    }
}
