<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableCobroValidacionFacturasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cobro_validacion_facturas', function (Blueprint $table) {
            $table->id();

            $table->uuid('grupo_validacion')->index();

            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->string('cliente_ruc', 20)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('tipo_ingreso', 30)->nullable();

            $table->string('factura', 50)->index();
            $table->date('fecha_vencimiento')->nullable();

            $table->decimal('monto_original_factura', 18, 2)->default(0);
            $table->decimal('saldo_cliente', 18, 2)->default(0);
            $table->decimal('valor_a_aplicar', 18, 2)->default(0);

            $table->decimal('sum_payments_gp', 18, 2)->default(0);
            $table->decimal('sum_pending_web', 18, 2)->default(0);
            $table->decimal('sum_devolutions', 18, 2)->default(0);
            $table->decimal('total_apply', 18, 2)->default(0);

            $table->decimal('saldo_factura', 18, 2)->default(0);
            $table->decimal('saldo_base', 18, 2)->default(0);
            $table->decimal('saldo_disponible', 18, 2)->default(0);
            $table->decimal('total_pending_not_accounted', 18, 2)->default(0);
            $table->decimal('excess_value', 18, 2)->default(0);
            $table->decimal('price_recommend', 18, 2)->default(0);

            $table->boolean('has_excess')->default(false);
            $table->boolean('has_double_apply')->default(false);

            $table->string('validation_type', 50)->nullable();
            $table->text('validation_message')->nullable();
            $table->string('double_apply_doc', 50)->nullable();

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
        Schema::dropIfExists('table_cobro_validacion_facturas');
    }
}
