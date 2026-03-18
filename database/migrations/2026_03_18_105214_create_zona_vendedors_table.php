<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZonaVendedorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('zona_vendedor', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion')->nullable();
            $table->boolean('estado')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->dateTime('modified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('gp_vendedors', function (Blueprint $table) {
            $table->foreignId('zona_vendedor_id')->nullable()->after('tipo_vendedor_id')->constrained('zona_vendedor')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zona_vendedors');
    }
}
