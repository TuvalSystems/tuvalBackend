<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hi_cotizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cot_id')->nullable()->constrained('gp_cotizacions')->nullOnDelete();
            $table->string('cotizacion', 20)->nullable()->index();
            $table->unsignedInteger('co_his')->default(1);
            $table->unique(['cot_id', 'co_his']);
            $table->foreignId('user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('gp_cliente_sucursals')->nullOnDelete();
            $table->foreignId('sucursal_facturacion_id')->nullable()->constrained('gp_cliente_sucursals')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('gp_clientes')->nullOnDelete();
            $table->foreignId('prospecto_id')->nullable()->constrained('gp_prospectos')->nullOnDelete();
            $table->enum('estado', ['borrador', 'app', 'espera', 'procesado', 'eliminado', 'rechazado', 'aprobado', 'caducado'])->default('espera');
            $table->boolean('facturado')->default(0);
            $table->boolean('factura_posterior_despacho')->default(0);
            $table->boolean('factura_a_demanda')->default(0);
            $table->boolean('factura_siguiente_mes')->default(0);
            $table->string('BOD_BODEGA', 20);
            $table->string('PTO_VTA_ID', 10);
            $table->string('PTO_VTA_DESCRIPCION', 65);
            $table->string('USERID', 20);
            $table->string('DESC_FORMA_PAGO_CODIGO', 10)->nullable();
            $table->string('CLI_RUC', 30);
            $table->string('CLI_RAZON_SOCIAL', 300)->nullable();
            $table->string('CLI_CORREO_ELECT_SRI', 200)->nullable();
            $table->string('COND_ID', 21)->nullable();
            $table->string('COND_ID_ORIGINAL', 21)->nullable();
            $table->string('VEND_ID', 15)->nullable();
            $table->string('VEND_NOMBRES', 20)->nullable();
            $table->string('VEND_APELLIDOS', 20)->nullable();
            $table->string('despacho', 20)->nullable();
            $table->decimal('porcentage_descuentooriginal', 11, 2);
            $table->decimal('porcentage_descuento', 11, 2);
            $table->decimal('descuento', 11, 2)->nullable();
            $table->decimal('subtotalmenosdescuento', 11, 2);
            $table->decimal('iva', 11, 2);
            $table->decimal('total', 11, 2);
            $table->enum('requiere_aprobacion_comercial', ['yes', 'no'])->default('no');
            $table->string('motivo_aprobacion_comercial', 2000)->nullable();
            $table->enum('aprobado_comercial', ['yes', 'no'])->default('no');
            $table->foreignId('aprobador_comercial_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->string('aprobado_comercial_mensaje', 300)->nullable();
            $table->dateTime('fecha_hora_aprobacion_comercial')->nullable();

            $table->enum('requiere_aprobacion_crediticia', ['yes', 'no'])->default('no');
            $table->enum('aprobado_crediticio', ['yes', 'no'])->default('no');
            $table->foreignId('aprobador_crediticio_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();

            $table->text('nota_interna')->nullable();
            $table->text('nota_cliente')->nullable();
            $table->text('nota_operaciones')->nullable();
            $table->string('orden_de_compra', 100)->nullable();

            $table->enum('impreso', ['yes', 'no'])->default('no')->nullable();
            $table->dateTime('fecha_impreso')->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->integer('impuesto')->nullable();

            $table->foreignId('cotizacion_venta_motivo_id')->nullable()->constrained('gp_cotizacion_venta_motivos')->nullOnDelete();
            $table->boolean('recordar')->default(0);
            $table->date('fecha_recordatorio')->nullable();
            $table->text('observacion')->nullable();
            $table->string('user_ing_recordatorio', 20)->nullable();

            $table->enum('origen', ['cotizacion', 'reserva', 'app_movil'])
                ->nullable()
                ->default('cotizacion')
                ->index();
            $table->dateTime('fe_facturacion_reserva')->nullable();

            $table->foreignId('pedido_reserva_id')->nullable()->constrained('gp_pedidos')->nullOnDelete();
            $table->dateTime('pedido_generado_at')->nullable();

            $table->foreignId('user_rechazo_id')->nullable()->constrained('gp_usuarios')->nullOnDelete()->index()->comment('Usuario que rechazo la cotizacion en estado de espera');
            $table->dateTime('fe_rechazo')->nullable();

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
        Schema::dropIfExists('hi_cotizacions');
    }
};
