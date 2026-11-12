<?php

declare(strict_types=1);

namespace Tests\Unit\Ventas\Cotizaciones;

use App\Models\Inventario\Articulos\GpArticulo;
use App\Models\Ventas\Clientes\GpCliente;
use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use App\Services\Ventas\Compartido\Contracts\OrderAndQuotationServiceInterface;
use App\Services\Ventas\Cotizaciones\Borradores\QuotationItemsDraftService;
use App\Services\Ventas\Cotizaciones\Builders\Contracts\QuotationBuilderInterface;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class QuotationItemsDraftPricesTest extends TestCase
{
    public function testPricesUseCustomerLevelAndPreserveRepeatedLinesAndQuantities(): void
    {
        $stock = $this->createMock(StockWarehouseServiceInterface::class);
        $stock->expects(self::once())->method('fetchListPricesArticle')
            ->with('A,B,')->willReturn([
                ['item' => 'A', 'idLista' => 'PUBLICO', 'valor' => 100],
                ['item' => 'A', 'idLista' => 'MAYORISTA', 'valor' => '12.34567'],
                ['item' => 'B', 'idLista' => 'PUBLICO', 'valor' => 80],
                ['item' => 'L', 'idLista' => 'MAYORISTA', 'valor' => 90],
            ]);
        $articles = [
            $this->article('A', 'N', 2),
            $this->article('A', 'N', 7),
            $this->article('B', 'N', 3),
            $this->article('L', 'L', 1),
        ];

        $this->service($stock)->price($articles, new GpCliente(['CLI_NIVEL_PRECIO' => 'MAYORISTA']));

        self::assertSame([12.3457, 12.3457, 0.0, 0.0], array_map(fn ($item) => $item->ART_VALOR, $articles));
        self::assertSame([12.3457, 12.3457, 0.0, 0.0], array_map(fn ($item) => $item->ART_VALOR_ORIGINAL, $articles));
        self::assertSame([2, 7, 3, 1], array_map(fn ($item) => $item->ART_CANTIDAD, $articles));
    }

    public function testOnlyPurchaseTypeLDoesNotRequestPrices(): void
    {
        $stock = $this->createMock(StockWarehouseServiceInterface::class);
        $stock->expects(self::never())->method('fetchListPricesArticle');
        $articles = [$this->article('L', 'L', 4)];

        $this->service($stock)->price($articles, new GpCliente(['CLI_NIVEL_PRECIO' => 'PUBLICO']));

        self::assertSame(0.0, $articles[0]->ART_VALOR);
        self::assertSame(0.0, $articles[0]->ART_VALOR_ORIGINAL);
    }

    public function testMissingCustomerPriceLevelIsRejected(): void
    {
        $originalApp = Facade::getFacadeApplication();
        $app = new Container();
        $app->instance('validator', new Factory(new Translator(new ArrayLoader(), 'es')));
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstance('validator');

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage('El cliente no tiene nivel de precio configurado.');
            $this->service($this->createMock(StockWarehouseServiceInterface::class))
                ->price([$this->article('L', 'L', 1)], new GpCliente());
        } finally {
            Facade::clearResolvedInstance('validator');
            Facade::setFacadeApplication($originalApp);
        }
    }

    private function article(string $code, string $type, int $quantity): GpArticulo
    {
        $article = new GpArticulo([
            'ART_CODIGO' => $code,
            'ART_TIPO_COMPRA' => $type,
            'ART_VALOR' => 999,
            'ART_VALOR_ORIGINAL' => 999,
        ]);
        $article->ART_CANTIDAD = $quantity;

        return $article;
    }

    private function service(StockWarehouseServiceInterface $stock): QuotationItemsDraftService
    {
        return new class(
            $this->createMock(QuotationBuilderInterface::class),
            $this->createMock(ApiGpService::class),
            $stock,
            $this->createMock(OrderAndQuotationServiceInterface::class),
        ) extends QuotationItemsDraftService {
            public function price(array $articles, GpCliente $client): void
            {
                $this->identifyGroupPricesToArticle($articles, $this->getPriceListGroupedByArticle($articles), $client);
            }
        };
    }
}
