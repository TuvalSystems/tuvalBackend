<?php

declare(strict_types=1);

namespace Tests\Unit\Reportes;

use App\Http\Requests\Reportes\ConsultarAprobacionesComercialesRequest;
use App\Repositories\Reportes\AprobacionesComercialesRepository;
use App\Services\Reportes\CommercialApprovalsService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Mockery;
use PHPUnit\Framework\TestCase;

class CommercialApprovalsParametersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    private function validator(array $input): \Illuminate\Contracts\Validation\Validator
    {
        $request = new class extends ConsultarAprobacionesComercialesRequest {
            public function normalize(): void { $this->prepareForValidation(); }
        };
        $request->initialize($input);
        $request->normalize();
        $factory = new Factory(new Translator(new ArrayLoader(), 'es'), new Container());
        return $factory->make($request->all(), $request->rules());
    }

    public function test_period_only_and_empty_optional_filters_are_valid(): void
    {
        $validator = $this->validator([
            'periodo' => ' 202611 ', 'cotizacion' => ' ', 'factura' => '',
            'cliente' => '', 'sucursal' => '', 'aprobador_final' => '',
        ]);
        self::assertTrue($validator->passes(), json_encode($validator->errors()->toArray()));
        self::assertSame('202611', $validator->validated()['periodo']);
        self::assertNull($validator->validated()['sucursal']);
    }

    public function test_missing_or_invalid_periods_are_rejected(): void
    {
        foreach ([null, '', '202600', '202613', '20261', '20261101', '000011', '999912', ['202611']] as $period) {
            self::assertTrue($this->validator(['periodo' => $period])->fails());
        }
    }

    public function test_legacy_parameters_are_rejected(): void
    {
        foreach (['tipo_documento' => 'PEDIDO', 'documento_id' => '486655'] as $key => $value) {
            self::assertTrue($this->validator(['periodo' => '202611', $key => $value])->fails());
        }
    }

    public function test_unsigned_ids_keep_precision_and_reject_invalid_values(): void
    {
        foreach (['sucursal', 'aprobador_final'] as $field) {
            self::assertTrue($this->validator(['periodo' => '202611', $field => '18446744073709551615'])->passes());
            foreach (['0', '-1', '1.5', '1e3', '01', '18446744073709551616', ['1']] as $value) {
                self::assertTrue($this->validator(['periodo' => '202611', $field => $value])->fails());
            }
        }
    }

    public function test_text_filters_preserve_leading_zeroes_and_have_length_limits(): void
    {
        $validator = $this->validator(['periodo' => '202611', 'cliente' => ' 0012345678001 ']);
        self::assertTrue($validator->passes());
        self::assertSame('0012345678001', $validator->validated()['cliente']);
        foreach (['cotizacion', 'factura', 'cliente'] as $field) {
            self::assertTrue($this->validator(['periodo' => '202611', $field => str_repeat('a', 101)])->fails());
        }
    }

    public function test_service_and_repository_bind_six_parameters_and_preserve_report_columns(): void
    {
        $connection = Mockery::mock();
        $database = Mockery::mock();
        $database->shouldReceive('connection')->once()->with('mysql')->andReturn($connection);
        $container = new Container();
        $container->instance('db', $database);
        Facade::setFacadeApplication($container);
        $connection->shouldReceive('select')->once()->with(
            'CALL tuvalapi.sp_consultar_aprobaciones_pedido(?, ?, ?, ?, ?, ?)',
            ['COT-1', '001-002-123', '0012345678001', '2', '18446744073709551615', '202611'],
        )->andReturn([(object) [
            'factura' => '001-002-123', 'punto_venta' => 'MATRIZ',
            'fuente_aprobador_final' => 'hi_cotizacion',
            'DetalleJson' => '{"id":18446744073709551615}',
        ]]);
        $service = new CommercialApprovalsService(new AprobacionesComercialesRepository());
        $rows = $service->getApprovals('COT-1', '001-002-123', '0012345678001', '2', '18446744073709551615', '202611');
        self::assertSame('MATRIZ', $rows[0]['punto_venta']);
        self::assertSame('hi_cotizacion', $rows[0]['fuente_aprobador_final']);
        self::assertSame('18446744073709551615', $rows[0]['DetalleJson']->id);
    }

    public function test_period_only_binds_null_filters_and_empty_result(): void
    {
        $connection = Mockery::mock();
        $database = Mockery::mock();
        $database->shouldReceive('connection')->once()->with('mysql')->andReturn($connection);
        $container = new Container();
        $container->instance('db', $database);
        Facade::setFacadeApplication($container);
        $connection->shouldReceive('select')->once()->with(
            'CALL tuvalapi.sp_consultar_aprobaciones_pedido(?, ?, ?, ?, ?, ?)',
            [null, null, null, null, null, '202611'],
        )->andReturn([]);
        $service = new CommercialApprovalsService(new AprobacionesComercialesRepository());
        self::assertSame([], $service->getApprovals(null, null, null, null, null, '202611'));
    }
}
