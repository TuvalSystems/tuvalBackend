<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrampasVaporsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trampas_vapors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('gp_clientes')->nullOnDelete();
            $table->string('proceso', 200);
            $table->string('tipo_trampa');
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
        Schema::dropIfExists('trampas_vapors');
    }
}
