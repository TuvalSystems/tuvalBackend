<?php

declare(strict_types=1);

namespace Tests\Unit\Reportes\Inventory;

use App\Http\Requests\Reportes\Inventory\ReplenishmentReceiptHistoryRequest;
use App\Repositories\Reportes\Inventory\Contracts\ReplenishmentReceiptHistoryRepositoryInterface;
use App\Repositories\Reportes\Inventory\ReplenishmentReceiptHistoryRepository;
use App\Services\Reportes\Inventory\ReplenishmentReceiptHistoryService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReplenishmentReceiptHistoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        DB::clearResolvedInstance('db');
        parent::tearDown();
    }

    public static function filterCases(): array
    {
        return [
            'period only' => [['periodo' => '202611'], true],
            'with number' => [['periodo' => '202611', 'numero_reposicion' => '000000000012672'], true],
            'null number' => [['periodo' => '202611', 'numero_reposicion' => null], true],
            'missing period' => [[], false],
            'invalid month' => [['periodo' => '202613'], false],
            'period array' => [['periodo' => ['202611']], false],
            'number array' => [['periodo' => '202611', 'numero_reposicion' => ['123']], false],
            'long number' => [['periodo' => '202611', 'numero_reposicion' => str_repeat('1', 256)], false],
        ];
    }

    #[DataProvider('filterCases')]
    public function testFilters(array $filters, bool $expected): void
    {
        $request = new ReplenishmentReceiptHistoryRequest();
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));

        self::assertTrue($request->authorize());
        self::assertSame($expected, $factory->make($filters, $request->rules())->passes());
    }

    public static function replenishmentNumbers(): array
    {
        return [[null], ['000000000012672'], ["x' OR 1=1 --"]];
    }

    #[DataProvider('replenishmentNumbers')]
    public function testRepositoryBindsFiltersAndPreservesRows(?string $replenishmentNumber): void
    {
        $rows = [(object) ['documento_reposicion' => '000000000012672', 'cantidad_ingresada' => '2.00']];
        $connection = Mockery::mock(ConnectionInterface::class);
        DB::shouldReceive('connection')->once()->with('mysql')->andReturn($connection);
        $connection->shouldReceive('select')->once()->with(
            'CALL tuvalapi.sp_historial_reposiciones_guia_ingreso(?, ?)',
            [$replenishmentNumber, '202611'],
        )->andReturn($rows);

        self::assertSame($rows, (new ReplenishmentReceiptHistoryRepository())->getHistory('202611', $replenishmentNumber));
    }

    public function testServiceSupportsPeriodOnlyAndEmptyResults(): void
    {
        $repository = $this->createMock(ReplenishmentReceiptHistoryRepositoryInterface::class);
        $repository->expects(self::once())->method('getHistory')->with('202611', null)->willReturn([]);

        self::assertSame([], (new ReplenishmentReceiptHistoryService($repository))->getHistory('202611'));
    }
}
