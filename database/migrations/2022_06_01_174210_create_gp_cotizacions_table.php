<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCotizacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_cotizacions', function (Blueprint $table) {
            $table->id();
            $table->string('cotizacion', 20)->unique()->nullable();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->bigInteger('sucursal_id')->unsigned()->nullable();
            $table->bigInteger('sucursal_facturacion_id')->unsigned()->nullable();
            $table->bigInteger('cliente_id')->unsigned()->nullable();
            $table->bigInteger('prospecto_id')->unsigned()->nullable();
            $table->enum('estado', ['espera', 'procesado', 'eliminado', 'rechazado', 'aprobado', 'caducado'])->default('espera');
            $table->boolean('factura_posterior_despacho')->nullable();
            $table->boolean('factura_a_demanda')->nullable();
            $table->boolean('factura_siguiente_mes')->nullable();
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
            $table->decimal('subtotal', 11, 2);
            $table->decimal('descuento', 11, 2)->nullable();
            $table->decimal('subtotalmenosdescuento', 11, 2);
            $table->decimal('iva', 11, 2);
            $table->decimal('total', 11, 2);
            $table->enum('requiere_aprobacion_comercial', ['yes', 'no'])->default('no');
            $table->string('motivo_aprobacion_comercial', 2000)->nullable();
            $table->enum('aprobado_comercial', ['yes', 'no'])->default('no');
            $table->bigInteger('aprobador_comercial_id')->unsigned()->nullable();
            $table->string('aprobado_comercial_mensaje', 300)->nullable();
            $table->enum('requiere_aprobacion_crediticia', ['yes', 'no'])->default('no');
            $table->enum('aprobado_crediticio', ['yes', 'no'])->default('no');
            $table->bigInteger('aprobador_crediticio_id')->unsigned()->nullable();
            $table->string('nota_interna', 2000)->nullable();
            $table->string('nota_cliente', 2000)->nullable();
            $table->string('orden_de_compra', 100)->nullable();
            $table->enum('impreso', ['yes', 'no'])->default('no')->nullable();
            $table->dateTime('fecha_impreso')->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->integer('impuesto')->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->foreign('user_id', 'gp_cotizacions_fk0')->references('id')->on('gp_usuarios')->onDelete('no action');
            $table->foreign('sucursal_id', 'gp_cotizacions_fk1')->references('id')->on('gp_cliente_sucursals')->onDelete('no action');
            $table->foreign('cliente_id', 'gp_cotizacions_fk2')->references('id')->on('gp_clientes')->onDelete('no action');
            $table->foreign('PTO_VTA_ID', 'gp_cotizacions_fk3')->references('PTO_VTA_ID')->on('gp_punto_ventas')->onDelete('no action');
            $table->foreign('DESC_FORMA_PAGO_CODIGO', 'gp_cotizacions_fk4')->references('DESC_FORMA_PAGO_CODIGO')->on('gp_descuento_forma_pagos')->onDelete('no action');
            $table->foreign('COND_ID', 'gp_cotizacions_fk5')->references('COND_ID')->on('gp_condicion_pagos')->onDelete('no action');
            $table->foreign('VEND_ID', 'gp_cotizacions_fk6')->references('VEND_ID')->on('gp_vendedors')->onDelete('no action');
            $table->foreign('aprobador_comercial_id', 'gp_cotizacions_fk7')->references('id')->on('gp_usuarios')->onDelete('no action');
            $table->foreign('aprobador_crediticio_id', 'gp_cotizacions_fk8')->references('id')->on('gp_usuarios')->onDelete('no action');
            $table->foreign('sucursal_facturacion_id', 'gp_cotizacions_fk9')->references('id')->on('gp_cliente_sucursals')->onDelete('no action');
            $table->foreign('BOD_BODEGA', 'gp_cotizacions_fk10')->references('BOD_BODEGA')->on('gp_bodegas')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('gp_cotizacions');
    }
}
