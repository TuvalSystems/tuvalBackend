<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin\Perfiles\Perfiles;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Ventas\Cotizaciones\Borradores\Contracts\QuotationItemsDraftServiceInterface;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

class TuvalMcpPilotTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app->instance('env', 'testing');
        $app['config']->set([
            'app.debug' => false,
            'tuval_mcp.enabled' => true,
            'cache.default' => 'array',
            'session.driver' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Schema::create('gp_punto_ventas', function (Blueprint $table) {
            $table->id();
            $table->string('PTO_VTA_ID');
            $table->string('PTO_VTA_DESCRIPCION');
            $table->string('PTO_VTA_ESTADO');
        });
        Schema::create('gp_relacion_usuario_punto_ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('USER_PTO_VTA_PUNTO_VENTA');
        });
        (require database_path('migrations/2026_11_12_140000_create_quotation_draft_pilot_requests_table.php'))->up();
        DB::table('gp_punto_ventas')->insert([
            ['id' => 2, 'PTO_VTA_ID' => '005', 'PTO_VTA_DESCRIPCION' => 'QA', 'PTO_VTA_ESTADO' => 'ACTIVO'],
            ['id' => 3, 'PTO_VTA_ID' => '006', 'PTO_VTA_DESCRIPCION' => 'Otro', 'PTO_VTA_ESTADO' => 'ACTIVO'],
        ]);
        DB::table('gp_relacion_usuario_punto_ventas')->insert(['user_id' => 179, 'USER_PTO_VTA_PUNTO_VENTA' => '005']);
        $this->app->instance(QuotationItemsDraftServiceInterface::class, $this->createMock(QuotationItemsDraftServiceInterface::class));
    }

    public function testAuthenticationAndTestEnvironmentAreRequired(): void
    {
        $this->postJson('/api/mcp/tuval', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
        $this->postJson('/api/borradores/items', $this->payload())->assertUnauthorized();
        $this->loginUser();
        $this->app->instance('env', 'production');
        $this->postJson('/api/mcp/tuval', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertNotFound();
        $this->postJson('/api/borradores/items', $this->payload())->assertNotFound();
    }

    public function testSwitchAndAccountRestrictionsAreEnforced(): void
    {
        $user = $this->loginUser();
        config(['tuval_mcp.enabled' => false]);
        $this->rpc('tools/list')->assertNotFound();
        config(['tuval_mcp.enabled' => true]);
        $user->status = 0;
        $this->rpc('tools/list')->assertForbidden();
        $user->status = 1;
        $user->must_change_password = 1;
        $this->rpc('tools/list')->assertForbidden();
        $user->must_change_password = 0;
        Sanctum::actingAs($user, ['mobile']);
        $this->rpc('tools/list')->assertForbidden();
    }

    public function testProtocolDiscoveryAndContextUseAuthenticatedUser(): void
    {
        $this->loginUser();
        $this->rpc('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'TUVAL tests', 'version' => '1'],
        ])->assertOk()->assertJsonPath('result.serverInfo.name', 'TUVAL Cotizaciones - Piloto');
        $this->rpc('tools/list')->assertOk()
            ->assertJsonPath('result.tools.0.name', 'tuval_contexto_cotizacion')
            ->assertJsonPath('result.tools.1.name', 'tuval_crear_borrador_cotizacion')
            ->assertJsonPath('result.tools.1.annotations.idempotentHint', true);
        $this->rpc('tools/call', ['name' => 'tuval_contexto_cotizacion', 'arguments' => ['pv_id' => 2]])
            ->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.usuario.id', 179)
            ->assertJsonPath('result.structuredContent.data.punto_venta.id', 2);
        $this->rpc('tools/call', ['name' => 'tuval_contexto_cotizacion', 'arguments' => ['pv_id' => 3]])
            ->assertOk()->assertJsonPath('result.isError', true);
    }

    public function testMcpAndFrontendShareIdempotencyAndRealUser(): void
    {
        $user = $this->loginUser();
        $drafts = $this->createMock(QuotationItemsDraftServiceInterface::class);
        $drafts->expects(self::once())->method('create')->with(
            self::callback(fn (array $data) => $data['pv_id'] === 2 && $data['detalle'][0]['cantidad'] === 2.0 && !isset($data['request_id'], $data['confirmado'])),
            self::identicalTo($user)
        )->willReturn($this->draftResult());
        $this->app->instance(QuotationItemsDraftServiceInterface::class, $drafts);
        $this->rpc('tools/call', ['name' => 'tuval_crear_borrador_cotizacion', 'arguments' => $this->payload()])
            ->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.data.numero', 'COT_TEST')
            ->assertJsonPath('result.structuredContent.data.replayed', false);
        $this->postJson('/api/borradores/items', $this->payload())->assertOk()
            ->assertJsonPath('data.numero', 'COT_TEST')->assertJsonPath('data.replayed', true);
        self::assertSame(1, DB::table('quotation_draft_pilot_requests')->count());
    }

    public function testUnauthorizedPointUnknownFieldsAndUnconfirmedInputCannotCreate(): void
    {
        $this->loginUser();
        $drafts = $this->createMock(QuotationItemsDraftServiceInterface::class);
        $drafts->expects(self::never())->method('create');
        $this->app->instance(QuotationItemsDraftServiceInterface::class, $drafts);
        $this->postJson('/api/borradores/items', [...$this->payload(), 'pv_id' => 3])->assertForbidden();
        foreach ([['user_id' => 6], ['ART_VALOR' => 1], ['confirmado' => false], ['request_id' => 'no-uuid'], ['detalle' => [['texto' => 'codo', 'cantidad' => 1.234]]]] as $override) {
            $this->postJson('/api/borradores/items', [...$this->payload(), ...$override])->assertUnprocessable();
        }
        $this->rpc('tools/call', ['name' => 'tuval_crear_borrador_cotizacion', 'arguments' => [...$this->payload(), 'user_id' => 6]])
            ->assertOk()->assertJsonPath('result.isError', true);
        self::assertSame(0, DB::table('quotation_draft_pilot_requests')->count());
    }

    public function testRequestIdCannotBeReusedWithDifferentPayload(): void
    {
        $this->loginUser();
        $drafts = $this->createMock(QuotationItemsDraftServiceInterface::class);
        $drafts->expects(self::once())->method('create')->willReturn($this->draftResult());
        $this->app->instance(QuotationItemsDraftServiceInterface::class, $drafts);
        $this->postJson('/api/borradores/items', $this->payload())->assertCreated();
        $this->postJson('/api/borradores/items', [...$this->payload(), 'cliente_ruc' => 'OTRO'])->assertConflict();
    }

    public function testFailureRollsBackIdempotencyRecordAndAllowsRetry(): void
    {
        $this->loginUser();
        $drafts = $this->createMock(QuotationItemsDraftServiceInterface::class);
        $attempt = 0;
        $drafts->expects(self::exactly(2))->method('create')->willReturnCallback(function () use (&$attempt): array {
            if (++$attempt === 1) {
                throw new \RuntimeException('Fallo simulado');
            }

            return $this->draftResult();
        });
        $this->app->instance(QuotationItemsDraftServiceInterface::class, $drafts);
        $this->postJson('/api/borradores/items', $this->payload())->assertConflict();
        self::assertSame(0, DB::table('quotation_draft_pilot_requests')->count());
        $this->postJson('/api/borradores/items', $this->payload())->assertCreated();
    }

    private function loginUser(): GpUsuario
    {
        $user = new GpUsuario();
        $user->forceFill(['id' => 179, 'USERID' => 'QA_USER', 'status' => 1, 'must_change_password' => 0]);
        $user->setRelation('perfil', (new Perfiles())->forceFill(['state' => 'active']));
        Sanctum::actingAs($user, ['web']);

        return $user;
    }

    private function rpc(string $method, array $params = [])
    {
        return $this->postJson('/api/mcp/tuval', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params,
        ], ['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-11-25']);
    }

    private function payload(): array
    {
        return [
            'request_id' => '6d281c32-b872-4c4b-a62e-128561014877',
            'confirmado' => true, 'cliente_ruc' => 'QA_CLIENT', 'pv_id' => 2,
            'detalle' => [['texto' => 'codo galvanizado 1/2 por 90', 'cantidad' => 2]],
        ];
    }

    private function draftResult(): array
    {
        return ['id' => 42, 'numero' => 'COT_TEST', 'incluidos' => [], 'omitidos' => [], 'advertencias' => []];
    }
}

