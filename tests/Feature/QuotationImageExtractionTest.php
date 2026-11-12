<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin\Perfiles\Perfiles;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Ventas\Cotizaciones\Borradores\QuotationDraftPilotService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuotationImageExtractionTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app->instance('env', 'testing');
        $app['config']->set([
            'app.debug' => false, 'tuval_mcp.enabled' => true,
            'cache.default' => 'array', 'session.driver' => 'array',
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'services.openai.key' => 'test-key-not-a-secret',
            'services.openai.quotation_model' => 'gpt-4.1-mini',
        ]);
        // Route providers resolve the limiter during bootstrap, before these overrides.
        $app->instance(RateLimiter::class, new RateLimiter($app['cache']->store('array')));

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Schema::create('gp_clientes', function (Blueprint $table): void {
            $table->id();
            $table->string('CLI_RUC');
            $table->string('CLI_ESTADO');
        });
        DB::table('gp_clientes')->insert(['CLI_RUC' => 'QA_CUSTOMER_PRIVATE', 'CLI_ESTADO' => 'ACTIVO']);
        $pilot = $this->createMock(QuotationDraftPilotService::class);
        $pilot->method('context')->willReturn(['usuario' => ['id' => 179], 'punto_venta' => ['id' => 2]]);
        $pilot->expects(self::never())->method('create');
        $this->app->instance(QuotationDraftPilotService::class, $pilot);
    }

    public function testExtractionKeepsFrontendContextAndReturnsReviewablePayload(): void
    {
        $user = $this->loginUser();
        $pilot = $this->createMock(QuotationDraftPilotService::class);
        $pilot->expects(self::once())->method('context')->with(2, self::identicalTo($user))
            ->willReturn(['usuario' => ['id' => 179], 'punto_venta' => ['id' => 2]]);
        $pilot->expects(self::never())->method('create');
        $this->app->instance(QuotationDraftPilotService::class, $pilot);
        $extraction = $this->extraction();
        $extraction['detalle'][] = ['texto' => 'neplo de 2 pulgadas', 'cantidad' => null, 'requiere_revision' => true, 'observacion' => null];
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->providerResponse($extraction))]);

        $this->upload()->assertOk()->assertJsonPath('data.usuario.id', 179)
            ->assertJsonPath('data.payload.cliente_ruc', 'QA_CUSTOMER_PRIVATE')
            ->assertJsonPath('data.payload.pv_id', 2)
            ->assertJsonPath('data.payload.detalle.0.texto', 'codo galvanizado 1/2 por 90')
            ->assertJsonPath('data.payload.detalle.0.cantidad', 2)
            ->assertJsonPath('data.payload.detalle.1.cantidad', null)
            ->assertJsonPath('data.revisiones.0.indice', 1)
            ->assertJsonPath('data.requiere_confirmacion', true);
        Http::assertSent(function ($request): bool {
            $body = $request->data();
            self::assertSame('gpt-4.1-mini', $body['model']);
            self::assertFalse($body['store']);
            self::assertTrue($body['text']['format']['strict']);
            self::assertStringStartsWith('data:image/png;base64,', $body['input'][0]['content'][1]['image_url']);
            self::assertStringNotContainsString('QA_CUSTOMER_PRIVATE', json_encode($body));
            self::assertStringNotContainsString('test-key-not-a-secret', json_encode($body));
            self::assertArrayNotHasKey('tools', $body);

            return $request->url() === 'https://api.openai.com/v1/responses';
        });
        Http::assertSentCount(1);
    }

    public function testProviderUsageIsReturnedAndRecordedWithoutDocumentContent(): void
    {
        $this->loginUser();
        $response = $this->providerResponse($this->extraction());
        $response['model'] = 'gpt-4.1-mini-2025-04-14';
        $response['usage'] = ['input_tokens' => 814, 'output_tokens' => 47, 'total_tokens' => 861,
            'input_tokens_details' => ['cached_tokens' => 100]];
        Log::shouldReceive('channel')->once()->with('quotation_ai')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('quotation_image_usage', \Mockery::on(function ($record) {
            return $record['userId'] === 179 && $record['pvId'] === 2
                && $record['usage']['totalTokens'] === 861
                && !str_contains(json_encode($record), 'QA_CUSTOMER_PRIVATE')
                && !str_contains(json_encode($record), 'codo galvanizado');
        }));
        Http::fake(['api.openai.com/*' => Http::response($response)]);
        $this->upload()->assertOk()->assertJsonPath('data.usage.totalTokens', 861)
            ->assertJsonPath('data.usage.cachedInputTokens', 100)
            ->assertJsonPath('data.usage.reasoningTokens', null);
    }

    public function testAuthenticationAndProductionGatePreventProviderCalls(): void
    {
        $this->upload()->assertUnauthorized();
        $this->loginUser();
        $this->app->instance('env', 'production');
        $this->upload()->assertNotFound();
        Http::assertNothingSent();
    }

    public function testInvalidFilesUnknownArgumentsAndOversizedUploadsAreRejected(): void
    {
        $this->loginUser();
        $this->upload(['imagenes' => [UploadedFile::fake()->create('test.pdf', 10, 'application/pdf')]])->assertUnprocessable();
        $this->upload(['imagenes' => array_fill(0, 4, $this->image())])->assertUnprocessable();
        $this->upload(['imagenes' => [UploadedFile::fake()->create('huge.png', 2049, 'image/png')]])->assertUnprocessable();
        $this->upload(['imagenes' => []])->assertUnprocessable();
        $this->upload(['model' => 'other-model'])->assertUnprocessable();
        $this->upload(['user_id' => 6])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function testPointOfSalePermissionAndClientAreCheckedBeforeSendingImages(): void
    {
        $this->loginUser();
        $this->upload(['cliente_ruc' => 'UNKNOWN'])->assertUnprocessable();
        // A new controller instance is needed because Laravel caches resolved controllers.
        $route = $this->app['router']->getRoutes()->getByName('borradores.images.extract');
        $route->flushController();
        $pilot = $this->createMock(QuotationDraftPilotService::class);
        $pilot->method('context')->willThrowException(new HttpException(403, 'Punto de venta no autorizado.'));
        $this->app->instance(QuotationDraftPilotService::class, $pilot);
        $this->upload(['pv_id' => 99])->assertForbidden();
        Http::assertNothingSent();
    }

    public function testMissingKeyReturnsServiceUnavailableWithoutCallingProvider(): void
    {
        $this->loginUser();
        config(['services.openai.key' => null]);
        $this->upload()->assertStatus(503);
        Http::assertNothingSent();
    }

    public function testProviderFailuresAreSanitizedAndNotRetried(): void
    {
        $this->loginUser();
        foreach ([401 => 503, 403 => 503, 429 => 503, 500 => 502] as $providerStatus => $expected) {
            Http::swap(new Factory());
            Http::preventStrayRequests();
            Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'PRIVATE_UPSTREAM_MARKER']], $providerStatus)]);
            $this->upload()->assertStatus($expected)->assertDontSee('PRIVATE_UPSTREAM_MARKER');
            Http::assertSentCount(1);
        }
    }

    public function testTimeoutReturnsGatewayTimeout(): void
    {
        $this->loginUser();
        Http::fake(fn () => throw new ConnectionException('PRIVATE_CONNECTION_MARKER'));
        $this->upload()->assertStatus(504)->assertDontSee('PRIVATE_CONNECTION_MARKER');
    }

    public function testExhaustedCreditsAreReportedWithoutExposingProviderData(): void
    {
        $this->loginUser();
        Http::fake(['api.openai.com/*' => Http::response(['error' => [
            'type' => 'insufficient_quota', 'code' => 'credit_balance_exhausted', 'message' => 'PRIVATE_BILLING_DETAIL',
        ]], 429)]);
        $this->upload()->assertStatus(503)->assertJsonPath('error', 'Ha ocurrido un error interno.')
            ->assertDontSee('PRIVATE_BILLING_DETAIL');
        Http::assertSentCount(1);
    }

    public function testMalformedIncompleteAndRefusedResultsCannotBecomePayloads(): void
    {
        $this->loginUser();
        $invalidQuantity = $this->extraction();
        $invalidQuantity['detalle'][0]['cantidad'] = 1.234;
        $unknownField = $this->extraction();
        $unknownField['precio'] = 1;
        foreach ([
            [['status' => 'incomplete'], 502],
            [['status' => 'completed', 'output' => []], 502],
            [$this->providerResponse($invalidQuantity), 502],
            [$this->providerResponse($unknownField), 502],
            [['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'PRIVATE_REFUSAL']]]]], 422],
            [$this->providerResponse([...$this->extraction(), 'excede_limite' => true]), 422],
        ] as [$body, $status]) {
            Http::swap(new Factory());
            Http::preventStrayRequests();
            Http::fake(['api.openai.com/*' => Http::response($body)]);
            $this->upload()->assertStatus($status)->assertDontSee('PRIVATE_REFUSAL');
        }
    }

    public function testEmptyExtractionDoesNotInventItems(): void
    {
        $this->loginUser();
        Http::fake(['api.openai.com/*' => Http::response($this->providerResponse([
            'detalle' => [], 'observaciones' => ['No hay artículos legibles.'], 'excede_limite' => false,
        ]))]);
        $this->upload()->assertOk()->assertJsonCount(0, 'data.payload.detalle')
            ->assertJsonPath('data.observaciones.0', 'No hay artículos legibles.');
    }

    public function testExtractionRateLimitStopsAdditionalProviderRequests(): void
    {
        $this->loginUser();
        Http::fake(['api.openai.com/*' => Http::response($this->providerResponse($this->extraction()))]);
        for ($i = 0; $i < 10; $i++) {
            $this->upload()->assertOk();
        }
        $this->upload()->assertStatus(429);
        Http::assertSentCount(10);
    }

    private function loginUser(): GpUsuario
    {
        $user = (new GpUsuario())->forceFill(['id' => 179, 'USERID' => 'QA_USER', 'status' => 1, 'must_change_password' => 0]);
        $user->setRelation('perfil', (new Perfiles())->forceFill(['state' => 'active']));
        Sanctum::actingAs($user, ['web']);

        return $user;
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('items.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    private function upload(array $override = [])
    {
        return $this->post('/api/borradores/extraer-imagenes', [
            'cliente_ruc' => 'QA_CUSTOMER_PRIVATE', 'pv_id' => 2, 'imagenes' => [$this->image()], ...$override,
        ], ['Accept' => 'application/json']);
    }

    private function extraction(): array
    {
        return ['detalle' => [[
            'texto' => 'codo galvanizado 1/2 por 90', 'cantidad' => 2,
            'requiere_revision' => false, 'observacion' => null,
        ]], 'observaciones' => [], 'excede_limite' => false];
    }

    private function providerResponse(array $extraction): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [
            ['type' => 'output_text', 'text' => json_encode($extraction)],
        ]]]];
    }
}
