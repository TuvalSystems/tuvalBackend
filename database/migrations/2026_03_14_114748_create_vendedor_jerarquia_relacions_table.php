<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendedorJerarquiaRelacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendedor_jerarquia_relaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_jerarquia_id')->constrained('vendedor_jerarquias')->cascadeOnDelete();
            $table->foreignId('tipo_vendedor_padre_id')->constrained('tipo_vendedors')->cascadeOnDelete();
            $table->foreignId('tipo_vendedor_hijo_id')->constrained('tipo_vendedors')->cascadeOnDelete();
            $table->boolean('estado')->default(1);
            $table->foreignId('created_by_user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->dateTime('modified_at')->nullable();
            $table->timestamps();

            $table->unique([
                'vendedor_jerarquia_id',
                'tipo_vendedor_padre_id',
                'tipo_vendedor_hijo_id'
            ], 'uq_vendedor_jerarquia_relacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vendedor_jerarquia_relaciones');
    }
}
