<?php

use App\Enums\Ventas\Vendedores\SellerAttributeTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendedorAtributosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendedor_atributos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->enum('tipo_dato', array_column(SellerAttributeTypeEnum::cases(), 'value'))->comment('string, boolean, integer, decimal, date, opcion');
            $table->boolean('es_multiple')->default(false);
            $table->boolean('es_requerido')->default(false);
            $table->boolean('estado')->default(true);
            $table->string('descripcion')->nullable();
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
        Schema::dropIfExists('vendedor_atributos');
    }
}
