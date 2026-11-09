<?php

namespace Tests\Feature;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\QuotationDraftSchema;
use Tests\TestCase;

class QuotationItemsDraftTest extends TestCase
{
    use QuotationDraftSchema;

    private const URL = '/api/cotizaciones/borrador-items';
    private $prices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDraftSchema();
        $this->seedDraftFixtures();
        Mail::fake(); Notification::fake(); Bus::fake();
        Http::preventStrayRequests();
        $this->prices = Mockery::mock(StockWarehouseServiceInterface::class);
        $this->prices->shouldNotReceive('fetchListPricesArticle');
        $this->app->instance(StockWarehouseServiceInterface::class, $this->prices);
    }

    private function login(array $abilities = ['web']): void
    {
        Sanctum::actingAs(GpUsuario::findOrFail(30), $abilities);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'request_id' => '5af09cd3-c181-461f-8637-c468f02d9638',
            'cliente_ruc' => '0990007020001', 'pv_id' => 7,
            'items' => [['codigo' => 'TEST-ITEM', 'cantidad' => 2, 'unidad' => 'UN']],
        ], $overrides);
    }

public function test_creates_minimal_header_details_and_safe_response_without_side_effects(): void
    {
        $this->login();
        $response = $this->postJson(self::URL, $this->payload())->assertCreated()
            ->assertJsonPath('data.numero', 'COT00000001')->assertJsonPath('data.estado', 'borrador_app')
            ->assertJsonPath('data.usuario.id', 30)->assertJsonPath('data.punto_venta.id', 7)
            ->assertJsonPath('data.incluidos.0.cantidad', 2)->assertJsonPath('data.incluidos.0.cantidad_asumida', false);
        $this->assertDatabaseHas('gp_cotizacions', ['id' => 1, 'user_id' => 30, 'pv_id' => 7, 'vendedor_id' => 3,
            'tipo_despacho_id' => 9, 'sucursal_id' => 4, 'estado' => 'borrador_app', 'subtotal' => 0,
            'descuento' => 0, 'subtotalmenosdescuento' => 0, 'iva' => 0, 'total' => 0, 'aprobado_comercial' => 'no', 'facturado' => 0]);
        $this->assertDatabaseHas('gp_cotizacion_detalles', ['art_id' => 5, 'ART_PLAN_UMEDIDA' => 'UN', 'ART_VALOR' => 0, 'ART_CANTIDAD' => 2]);
        $this->assertDatabaseCount('quotation_item_draft_requests', 1);
        foreach (['ART_COSTO_PROMEDIO', 'margen', 'ART_VALOR', 'password'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        Mail::assertNothingSent(); Mail::assertNothingQueued(); Notification::assertNothingSent(); Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_missing_quantity_is_one_and_marked_assumed(): void
    {
        $this->login();
        $this->postJson(self::URL, $this->payload(['items' => [['codigo' => 'TEST-ITEM']]]))->assertCreated()
            ->assertJsonPath('data.incluidos.0.cantidad', 1)->assertJsonPath('data.incluidos.0.cantidad_asumida', true)
            ->assertJsonPath('data.advertencias.0.codigo', 'cantidad_asumida');
    }

    public function test_retry_reuses_persisted_result_without_new_rows(): void
    {
        $this->login();
        $this->postJson(self::URL, $this->payload())->assertCreated();
        DB::table('gp_clientes')->update(['CLI_NIVEL_PRECIO' => null]);
        $data = $this->payload(); $data['items'][0]['cantidad'] = '2.00';
        $this->postJson(self::URL, $data)->assertOk()->assertJsonPath('data.id', 1)->assertJsonPath('data.reutilizado', true);
        $this->assertDatabaseCount('gp_cotizacions', 1); $this->assertDatabaseCount('gp_cotizacion_detalles', 1);
    }

    public function test_reusing_key_with_changed_content_conflicts(): void
    {
        $this->login();
        $this->postJson(self::URL, $this->payload())->assertCreated();
        $data = $this->payload(); $data['items'][0]['cantidad'] = 3;
        $this->postJson(self::URL, $data)->assertConflict();
        $this->assertDatabaseCount('gp_cotizacions', 1);
    }

    public function test_all_items_must_exist_before_writing(): void
    {
        $this->login(); $this->prices->shouldNotReceive('fetchListPricesArticle');
        $this->postJson(self::URL, $this->payload(['items' => [['codigo' => 'TEST-ITEM'], ['codigo' => 'MISSING']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.codigo');
        $this->assertDatabaseCount('gp_cotizacions', 0); $this->assertDatabaseCount('quotation_item_draft_requests', 0);
    }

    public static function invalidRequests(): array
    {
        return [
            'zero' => [['items' => [['codigo' => 'TEST-ITEM', 'cantidad' => 0]]], 'items.0.cantidad'],
            'negative' => [['items' => [['codigo' => 'TEST-ITEM', 'cantidad' => -1]]], 'items.0.cantidad'],
            'third decimal' => [['items' => [['codigo' => 'TEST-ITEM', 'cantidad' => 1.001]]], 'items.0.cantidad'],
            'null quantity' => [['items' => [['codigo' => 'TEST-ITEM', 'cantidad' => null]]], 'items.0.cantidad'],
            'exponent' => [['items' => [['codigo' => 'TEST-ITEM', 'cantidad' => '1e2']]], 'items.0.cantidad'],
            'duplicates' => [['items' => [['codigo' => 'TEST-ITEM'], ['codigo' => 'TEST-ITEM']]], 'items.0.codigo'],
            'impersonation' => [['user_id' => 30], 'user_id'],
            'client required' => [['cliente_ruc' => null], 'cliente_ruc'],
            'key required' => [['request_id' => null], 'request_id'],
            'price injection' => [['items' => [['codigo' => 'TEST-ITEM', 'precio' => 1]]], 'items.0'],
            'resolver candidates' => [['renglones' => [['estado' => 'ambiguo', 'candidatos' => []]]], 'renglones'],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function test_rejects_invalid_input_before_persistence(array $overrides, string $error): void
    {
        $this->login(); $this->prices->shouldNotReceive('fetchListPricesArticle');
        $this->postJson(self::URL, $this->payload($overrides))->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public static function invalidCatalog(): array
    {
        return [
            'inactive article' => ['gp_articulos', ['ART_ESTADO' => 'INACTIVO'], 'items.0.codigo'],
            'logistic service' => ['gp_articulos', ['es_servicio_logistico' => 1], 'items.0.codigo'],
            'inactive client' => ['gp_clientes', ['CLI_ESTADO' => 'INACTIVO'], 'cliente_ruc'],

];
    }

    #[DataProvider('invalidCatalog')]
    public function test_rejects_inactive_catalog_entries(string $table, array $changes, string $error): void
    {
        $this->login(); DB::table($table)->update($changes);
        $this->prices->shouldNotReceive('fetchListPricesArticle');
        $this->postJson(self::URL, $this->payload())->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public function test_respects_explicit_client_and_does_not_fallback(): void
    {
        $this->login(); $this->prices->shouldNotReceive('fetchListPricesArticle');
        $this->postJson(self::URL, $this->payload(['cliente_ruc' => 'OTHER-CLIENT']))->assertUnprocessable()->assertJsonValidationErrors('cliente_ruc');
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public function test_rejects_incompatible_units_and_fractional_pieces(): void
    {
        $this->login();
        $this->postJson(self::URL, $this->payload(['items' => [['codigo' => 'TEST-ITEM', 'unidad' => 'CAJA']]]))->assertUnprocessable()->assertJsonValidationErrors('items.0.unidad');
        $this->postJson(self::URL, $this->payload(['items' => [['codigo' => 'TEST-ITEM', 'cantidad' => 1.5]]]))->assertUnprocessable()->assertJsonValidationErrors('items.0.cantidad');
    }

    public function test_allows_supported_decimals_without_rounding(): void
    {
        $this->login();
        DB::table('gp_articulos')->update(['ART_DECIMAL_CANTIDAD' => 2]);
        DB::table('gp_unidad_medidas')->update(['UMEDIDA_CANTIDAD_DECIMAL' => 2]);
        $this->postJson(self::URL, $this->payload(['items' => [['codigo' => 'TEST-ITEM', 'cantidad' => 1.25]]]))->assertCreated();
        $this->assertDatabaseHas('gp_cotizacion_detalles', ['ART_CANTIDAD' => 1.25]);
    }

public function test_multiple_items_are_all_saved_in_the_received_order(): void
    {
        $this->login();
        $second = (array) DB::table('gp_articulos')->first();
        $second['id'] = 6; $second['ART_CODIGO'] = 'TEST-SECOND';
        DB::table('gp_articulos')->insert($second);
        $this->postJson(self::URL, $this->payload(['items' => [['codigo' => 'TEST-SECOND', 'cantidad' => 3], ['codigo' => 'TEST-ITEM']]]))->assertCreated()
            ->assertJsonPath('data.incluidos.0.codigo', 'TEST-SECOND')->assertJsonPath('data.incluidos.1.codigo', 'TEST-ITEM');
        $this->assertDatabaseCount('gp_cotizacion_detalles', 2);
        $this->assertDatabaseHas('gp_cotizacions', ['subtotal' => 0, 'total' => 0]);
    }

    public function test_minimal_draft_succeeds_without_price_level_or_complete_commercial_configuration(): void
    {
        $this->login();
        DB::table('gp_clientes')->update(['CLI_NIVEL_PRECIO' => null]);
        DB::table('clientes_configuraciones')->delete();
        DB::table('gp_vendedors')->delete();
        DB::table('gp_cliente_sucursals')->delete();
        DB::table('gp_descuento_forma_pagos')->delete();
        DB::table('tipo_despachos')->delete();
        DB::table('gp_articulos')->update(['ART_TIPO_COMPRA' => 'L']);
        $this->postJson(self::URL, $this->payload())->assertCreated()->assertJsonPath('data.estado', 'borrador_app');
        $this->assertDatabaseHas('gp_cotizacions', ['estado' => 'borrador_app', 'vendedor_id' => null,
            'sucursal_id' => null, 'tipo_despacho_id' => null, 'DESC_FORMA_PAGO_CODIGO' => null, 'total' => 0]);
        $this->assertDatabaseHas('gp_cotizacion_detalles', ['ART_CODIGO' => 'TEST-ITEM', 'ART_CANTIDAD' => 2, 'ART_TIPO_COMPRA' => 'L']);
        Http::assertNothingSent();
    }

    public function test_failure_after_details_rolls_back_header_details_and_key(): void
    {
        $this->login();
        \App\Models\Ventas\Cotizaciones\GpCotizacionDetalle::created(function () {
            $this->assertDatabaseCount('gp_cotizacions', 1); $this->assertDatabaseCount('gp_cotizacion_detalles', 1);
            throw new \LogicException('PRIVATE-DIAGNOSTIC');
        });
        $this->postJson(self::URL, $this->payload())->assertStatus(500)->assertDontSee('PRIVATE-DIAGNOSTIC');
        $this->assertDatabaseCount('gp_cotizacions', 0); $this->assertDatabaseCount('gp_cotizacion_detalles', 0);
        $this->assertDatabaseCount('quotation_item_draft_requests', 0);
    }

    public function test_authentication_and_web_ability_are_required(): void
    {
        $this->postJson(self::URL, $this->payload())->assertUnauthorized();
        $this->login(['mobile']); $this->postJson(self::URL, $this->payload())->assertForbidden();
    }

    public function test_active_user_profile_and_assigned_point_of_sale_are_required_on_retries_too(): void
    {
        $this->login();
        $this->postJson(self::URL, $this->payload())->assertCreated();
        DB::table('gp_relacion_usuario_punto_ventas')->delete();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        DB::table('gp_usuarios')->update(['status' => 0]); $this->login();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        DB::table('gp_usuarios')->update(['status' => 1]); DB::table('perfiles')->update(['state' => 'inactive']); $this->login();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        $this->assertDatabaseCount('gp_cotizacions', 1);
    }

    public function test_two_processes_with_same_key_create_one_quote(): void
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required for the two-process SQLite test.');
        }
        $database = sys_get_temp_dir().DIRECTORY_SEPARATOR.'quotation-draft-test-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($database);
        try {
            config(['database.connections.draft_race' => ['driver' => 'sqlite', 'database' => $database,
                'prefix' => '', 'foreign_key_constraints' => true]]);
            DB::setDefaultConnection('draft_race');
            $this->createDraftSchema(); $this->seedDraftFixtures();
            DB::statement('PRAGMA journal_mode=WAL');
            $processes = [];
            for ($i = 0; $i < 2; $i++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/quotation-draft-worker.php'), $database],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }
            $reused = [];
            foreach ($processes as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $output.$error);
                $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame(1, $result['id']); $reused[] = $result['reutilizado'];
            }
            sort($reused);
            $this->assertSame([false, true], $reused);
            $this->assertDatabaseCount('gp_cotizacions', 1);
            $this->assertDatabaseCount('gp_cotizacion_detalles', 1);
            $this->assertDatabaseCount('quotation_item_draft_requests', 1);
        } finally {
            DB::purge('draft_race');
            foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
                if (is_file($file)) { unlink($file); }
            }
        }
    }

    private function enablePilot(array $abilities = ['web', 'quotation-items:pilot']): void
    {
        DB::table('gp_usuarios')->insert(['id' => 31, 'USERID' => 'TEST-ACTOR', 'status' => 1, 'perfil_id' => 1]);
        DB::table('gp_relacion_usuario_punto_ventas')->insert(['user_id' => 31, 'USER_PTO_VTA_PUNTO_VENTA' => 'TEST-PV']);
        config(['quotation_item_drafts.pilot' => [
            'enabled' => true, 'verified_app_url' => config('app.url'), 'owner_user_id' => 30,
            'actor_user_ids' => [31], 'default_pv_description' => 'California (fixture)',
            'default_client_ruc' => '0990007020001',
        ]]);
        Sanctum::actingAs(GpUsuario::findOrFail(31), $abilities);
    }

    private function pilotPayload(array $overrides = []): array
    {
        return array_replace(['request_id' => '5af09cd3-c181-461f-8637-c468f02d9638',
            'modo_prueba' => true, 'user_id' => 30, 'items' => [['codigo' => 'TEST-ITEM']]], $overrides);
    }

    public function test_restricted_pilot_uses_owner_30_and_resolves_test_defaults_without_impersonating_actor(): void
    {
        $this->enablePilot();
        $this->postJson(self::URL, $this->pilotPayload())->assertCreated()
            ->assertJsonPath('data.usuario.id', 30)->assertJsonPath('data.actor.id', 31)
            ->assertJsonPath('data.punto_venta.id', 7)->assertJsonPath('data.cliente.ruc', '0990007020001')
            ->assertJsonPath('data.modo_prueba', true)->assertJsonPath('data.incluidos.0.cantidad_asumida', true);
        $this->assertDatabaseHas('gp_cotizacions', ['user_id' => 30, 'USERID' => 'TEST']);
        $this->assertDatabaseHas('quotation_item_draft_requests', ['user_id' => 31, 'owner_id' => 30]);
        $this->postJson(self::URL, $this->pilotPayload())->assertOk()->assertJsonPath('data.usuario.id', 30)->assertJsonPath('data.reutilizado', true);
        $this->assertDatabaseCount('gp_cotizacions', 1);
        Mail::assertNothingSent(); Mail::assertNothingQueued(); Notification::assertNothingSent(); Bus::assertNothingDispatched();
    }

    public function test_pilot_owner_can_be_omitted_and_still_uses_only_the_configured_owner(): void
    {
        $this->enablePilot();
        $data = $this->pilotPayload(); unset($data['user_id']);
        $this->postJson(self::URL, $data)->assertCreated()->assertJsonPath('data.usuario.id', 30);
    }

    public function test_pilot_never_overrides_an_explicit_client(): void
    {
        $this->enablePilot();
        $client = (array) DB::table('gp_clientes')->first(); $client['id'] = 8; $client['CLI_RUC'] = 'EXPLICIT-CLIENT';
        DB::table('gp_clientes')->insert($client);
        DB::table('clientes_configuraciones')->insert(['cliente_id' => 8, 'pv_id' => 7, 'vendedor_id' => 3, 'estado' => 1]);
        DB::table('gp_cliente_sucursals')->insert(['id' => 11, 'cliente_id' => 8, 'CLI_SUC_ID_DIRECCION' => 'PRINCIPAL', 'estado' => 1]);
        $this->postJson(self::URL, $this->pilotPayload(['cliente_ruc' => 'EXPLICIT-CLIENT']))->assertCreated()
            ->assertJsonPath('data.cliente.ruc', 'EXPLICIT-CLIENT');
        $this->assertDatabaseHas('gp_cotizacions', ['cliente_id' => 8, 'CLI_RUC' => 'EXPLICIT-CLIENT', 'sucursal_id' => 11]);
    }

    public function test_pilot_does_not_replace_invalid_or_null_explicit_clients(): void
    {
        $this->enablePilot();
        $this->postJson(self::URL, $this->pilotPayload(['cliente_ruc' => 'UNKNOWN']))->assertUnprocessable()->assertJsonValidationErrors('cliente_ruc');
        $this->postJson(self::URL, $this->pilotPayload(['cliente_ruc' => null]))->assertUnprocessable()->assertJsonValidationErrors('cliente_ruc');
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public static function deniedPilotSettings(): array
    {
        return [
            'disabled' => ['enabled', false], 'no verified destination' => ['verified_app_url', ''],
            'another destination' => ['verified_app_url', 'https://another.invalid'],
            'actor not allowed' => ['actor_user_ids', [30]], 'no target owner' => ['owner_user_id', 0],
        ];
    }

    #[DataProvider('deniedPilotSettings')]
    public function test_pilot_requires_explicit_safe_configuration(string $setting, mixed $value): void
    {
        $this->enablePilot(); config(["quotation_item_drafts.pilot.$setting" => $value]);
        $this->postJson(self::URL, $this->pilotPayload())->assertForbidden();
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public function test_pilot_is_denied_in_production_even_with_all_flags_and_permissions(): void
    {
        $this->enablePilot(); $this->app->instance('env', 'production');
        $this->postJson(self::URL, $this->pilotPayload())->assertForbidden();
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public function test_pilot_requires_its_token_ability_and_refuses_other_owners(): void
    {
        $this->enablePilot(['web']);
        $this->postJson(self::URL, $this->pilotPayload())->assertForbidden();
        Sanctum::actingAs(GpUsuario::findOrFail(31), ['web', 'quotation-items:pilot']);
        $this->postJson(self::URL, $this->pilotPayload(['user_id' => 31]))->assertForbidden();
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public function test_pilot_checks_owner_activity_and_both_users_point_of_sale_permissions(): void
    {
        $this->enablePilot();
        DB::table('gp_usuarios')->where('id', 30)->update(['status' => 0]);
        $this->postJson(self::URL, $this->pilotPayload())->assertForbidden();
        DB::table('gp_usuarios')->where('id', 30)->update(['status' => 1]);
        DB::table('gp_relacion_usuario_punto_ventas')->where('user_id', 30)->delete();
        $this->postJson(self::URL, $this->pilotPayload())->assertForbidden();
        DB::table('gp_relacion_usuario_punto_ventas')->insert(['user_id' => 30, 'USER_PTO_VTA_PUNTO_VENTA' => 'TEST-PV']);
        DB::table('gp_relacion_usuario_punto_ventas')->where('user_id', 31)->delete();
        $this->postJson(self::URL, $this->pilotPayload())->assertForbidden();
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public function test_pilot_fails_instead_of_inventing_missing_or_ambiguous_california_ids(): void
    {
        $this->enablePilot();
        config(['quotation_item_drafts.pilot.default_pv_description' => 'MISSING']);
        $this->postJson(self::URL, $this->pilotPayload())->assertUnprocessable()->assertJsonValidationErrors('pv_id');
        config(['quotation_item_drafts.pilot.default_pv_description' => 'California (fixture)']);
        DB::table('gp_punto_ventas')->insert(['id' => 70, 'PTO_VTA_ID' => 'ANOTHER', 'PTO_VTA_DESCRIPCION' => 'California (fixture)', 'PTO_VTA_ESTADO' => 'ACTIVO']);
        $this->postJson(self::URL, $this->pilotPayload())->assertUnprocessable()->assertJsonValidationErrors('pv_id');
        $this->assertDatabaseCount('gp_cotizacions', 0);
    }

    public function test_idempotency_key_belongs_to_actor_and_cannot_switch_to_his_own_owner(): void
    {
        $this->enablePilot();
        $this->postJson(self::URL, $this->pilotPayload())->assertCreated();
        $this->postJson(self::URL, $this->payload())->assertConflict();
        $this->assertDatabaseCount('gp_cotizacions', 1);
    }

}
