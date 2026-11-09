<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Isolated subset of the commercial schema. Historical full migrations require GP/MySQL.
trait QuotationDraftSchema
{
    protected function createDraftSchema(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $database = DB::connection()->getDatabaseName();
        $this->assertTrue($database === ':memory:' || (str_starts_with($database, sys_get_temp_dir().DIRECTORY_SEPARATOR.'quotation-draft-test-') && is_file($database)));
        Schema::create('perfiles', function (Blueprint $t) {
            $t->id(); $t->string('state'); $t->timestamps();
        });
        Schema::create('gp_usuarios', function (Blueprint $t) {
            $t->id(); $t->string('USERID'); $t->boolean('status'); $t->foreignId('perfil_id');
            $t->boolean('must_change_password')->default(false); $t->timestamps();
        });
        Schema::create('gp_punto_ventas', function (Blueprint $t) {
            $t->id(); $t->string('PTO_VTA_ID')->unique(); $t->string('PTO_VTA_DESCRIPCION'); $t->string('PTO_VTA_ESTADO'); $t->timestamps();
        });
        Schema::create('gp_relacion_usuario_punto_ventas', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->string('USER_PTO_VTA_PUNTO_VENTA');
        });
        Schema::create('gp_relacion_punto_venta_bodegas', function (Blueprint $t) {
            $t->id(); $t->string('PTO_VTA_BOD_PUNTO_VENTA'); $t->string('PTO_VTA_BOD_BODEGA');
        });
        Schema::create('gp_vendedors', function (Blueprint $t) {
            $t->id(); $t->string('VEND_ID')->unique(); $t->string('VEND_NOMBRES'); $t->string('VEND_APELLIDOS');
        });
        Schema::create('gp_clientes', function (Blueprint $t) {
            $t->id(); $t->string('CLI_RUC')->unique(); $t->string('CLI_ESTADO'); $t->string('CLI_RAZON_SOCIAL');
            $t->string('CLI_CORREO_ELECT_SRI')->nullable(); $t->string('CLI_CONDICION_PAGO');
            $t->string('CLI_NIVEL_PRECIO')->nullable(); $t->decimal('CLI_DSTO_COMERCIAL', 11, 2); $t->timestamps();
        });
        Schema::create('clientes_configuraciones', function (Blueprint $t) {
            $t->id(); $t->foreignId('cliente_id'); $t->foreignId('pv_id'); $t->foreignId('vendedor_id'); $t->boolean('estado');
        });
        Schema::create('gp_cliente_sucursals', function (Blueprint $t) {
            $t->id(); $t->foreignId('cliente_id'); $t->string('CLI_SUC_ID_DIRECCION'); $t->boolean('estado');
        });
        Schema::create('gp_descuento_forma_pagos', function (Blueprint $t) {
            $t->id(); $t->string('DESC_FORMA_PAGO_CONDICION_PAGO'); $t->string('DESC_FORMA_PAGO_CODIGO')->unique();
        });
        Schema::create('gp_condicion_pagos', function (Blueprint $t) { $t->id(); $t->string('COND_ID')->unique(); });
        Schema::create('gp_bodegas', function (Blueprint $t) { $t->id(); $t->string('BOD_BODEGA')->unique(); });
        Schema::create('tipo_despachos', function (Blueprint $t) {
            $t->id(); $t->string('descripcion'); $t->boolean('estado'); $t->timestamps();
        });
        Schema::create('tipo_despacho_punto_ventas', function (Blueprint $t) {
            $t->foreignId('pv_id'); $t->foreignId('tipo_despacho_id'); $t->timestamps();
        });
        Schema::create('gp_articulos', function (Blueprint $t) {
            $t->id(); $t->string('ART_CODIGO')->unique(); $t->string('ART_CODIGO_TRES_LETRAS'); $t->string('ART_DESCRIPCION');
            $t->string('ART_ESTADO'); $t->string('ART_CLASE_ARTICULO'); $t->string('ART_TIPO_COMPRA');
            $t->integer('ART_DECIMAL_CANTIDAD'); $t->string('ART_PLAN_UMEDIDA'); $t->decimal('ART_IMP_VTA', 11, 2);
            $t->decimal('ART_COSTO_PROMEDIO', 11, 2); $t->boolean('disponible')->default(true); $t->boolean('es_servicio_logistico')->default(false);
        });
        Schema::create('gp_unidad_medidas', function (Blueprint $t) {
            $t->id(); $t->string('UMEDIDA_PLAN'); $t->string('UMEDIDA_BASE'); $t->integer('UMEDIDA_CANTIDAD_DECIMAL');
        });
        require_once database_path('migrations/2022_06_01_174210_create_gp_cotizacions_table.php');
        (new \CreateGpCotizacionsTable)->up();
        require_once database_path('migrations/2022_06_01_215810_create_gp_cotizacion_detalles_table.php');
        (new \CreateGpCotizacionDetallesTable)->up();
        Schema::table('gp_cotizacions', function (Blueprint $t) {
            $t->foreignId('pv_id')->nullable(); $t->foreignId('vendedor_id')->nullable(); $t->foreignId('tipo_despacho_id')->nullable();
            $t->boolean('facturado')->default(false); $t->string('origen')->nullable(); $t->string('nota_operaciones')->nullable();
            $t->decimal('modificador_global', 11, 2)->default(0); $t->foreignId('cotizacion_venta_motivo_id')->nullable();
        });
        Schema::table('gp_cotizacion_detalles', function (Blueprint $t) {
            $t->foreignId('art_id')->nullable(); $t->string('lista_precio')->nullable(); $t->boolean('CHECK_ALTERNATIVO')->default(false);
            $t->decimal('valor_original_l', 11, 4)->default(0); $t->string('cod_categoria')->nullable();
            $t->string('cod_linea')->nullable(); $t->string('cod_clase')->nullable(); $t->string('resultado_configuracion_item_l')->nullable();
        });
        (require database_path('migrations/2026_11_09_000001_create_quotation_item_draft_requests_table.php'))->up();
    }

    protected function seedDraftFixtures(): void
    {
        // Synthetic local IDs only. California's real ID must be looked up in verified QA.
        DB::table('perfiles')->insert(['id' => 1, 'state' => 'active']);
        DB::table('gp_usuarios')->insert(['id' => 30, 'USERID' => 'TEST', 'status' => 1, 'perfil_id' => 1]);
        DB::table('gp_punto_ventas')->insert(['id' => 7, 'PTO_VTA_ID' => 'TEST-PV', 'PTO_VTA_DESCRIPCION' => 'California (fixture)', 'PTO_VTA_ESTADO' => 'ACTIVO']);
        DB::table('gp_relacion_usuario_punto_ventas')->insert(['user_id' => 30, 'USER_PTO_VTA_PUNTO_VENTA' => 'TEST-PV']);
        DB::table('gp_bodegas')->insert(['BOD_BODEGA' => 'TEST-BOD']);
        DB::table('gp_relacion_punto_venta_bodegas')->insert(['PTO_VTA_BOD_PUNTO_VENTA' => 'TEST-PV', 'PTO_VTA_BOD_BODEGA' => 'TEST-BOD']);
        DB::table('gp_vendedors')->insert(['id' => 3, 'VEND_ID' => 'TEST-VEND', 'VEND_NOMBRES' => 'Prueba', 'VEND_APELLIDOS' => 'Local']);
        DB::table('gp_condicion_pagos')->insert(['COND_ID' => 'CONTADO']);
        DB::table('gp_clientes')->insert(['id' => 2, 'CLI_RUC' => '0990007020001', 'CLI_ESTADO' => 'ACTIVO', 'CLI_RAZON_SOCIAL' => 'Fixture', 'CLI_CONDICION_PAGO' => 'CONTADO', 'CLI_NIVEL_PRECIO' => 'P1', 'CLI_DSTO_COMERCIAL' => 10]);
        DB::table('clientes_configuraciones')->insert(['cliente_id' => 2, 'pv_id' => 7, 'vendedor_id' => 3, 'estado' => 1]);
        DB::table('gp_cliente_sucursals')->insert(['id' => 4, 'cliente_id' => 2, 'CLI_SUC_ID_DIRECCION' => 'PRINCIPAL', 'estado' => 1]);
        DB::table('gp_descuento_forma_pagos')->insert(['DESC_FORMA_PAGO_CONDICION_PAGO' => 'CONTADO', 'DESC_FORMA_PAGO_CODIGO' => 'EFECTIVO']);
        DB::table('tipo_despachos')->insert(['id' => 9, 'descripcion' => 'Retiro en bodega', 'estado' => 1]);
        DB::table('tipo_despacho_punto_ventas')->insert(['pv_id' => 7, 'tipo_despacho_id' => 9]);
        DB::table('gp_unidad_medidas')->insert(['UMEDIDA_PLAN' => 'PLAN-UN', 'UMEDIDA_BASE' => 'UN', 'UMEDIDA_CANTIDAD_DECIMAL' => 0]);
        DB::table('gp_articulos')->insert(['id' => 5, 'ART_CODIGO' => 'TEST-ITEM', 'ART_CODIGO_TRES_LETRAS' => 'TST', 'ART_DESCRIPCION' => 'Artículo ficticio', 'ART_ESTADO' => 'ACTIVO', 'ART_CLASE_ARTICULO' => 'VENTA', 'ART_TIPO_COMPRA' => 'N', 'ART_DECIMAL_CANTIDAD' => 0, 'ART_PLAN_UMEDIDA' => 'PLAN-UN', 'ART_IMP_VTA' => 15, 'ART_COSTO_PROMEDIO' => 4]);
    }
}
