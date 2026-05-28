<?php

namespace App\Services\Movil\Gestion;

use App\Models\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpCotizacion;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Models\GpUsuario;
use App\Services\Api\Bodegas\ApiGestionArticleService;
use App\Services\Movil\Gestion\Contracts\SteamTrapServiceInterface;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SteamTrapService implements SteamTrapServiceInterface
{
    public function __construct(
        protected readonly ApiGestionArticleService $api,
        protected readonly ClientQueryServiceInterface $client
    ) {}

    public function generateSizingPdf(array $data): string
    {
        $clienteId          = data_get($data, 'customer_id');
        $params             = data_get($data, 'spParams', []);
        $info               = $this->getSteamTrapItems($params);
        $selectedItem       = data_get($info, 'selectedItem');
        if (empty($selectedItem)) {
            throw new \RuntimeException('No se encontró un artículo recomendado.');
        }
        $originItems        = data_get($info, 'originItems', []);
        $itemCode           = data_get($selectedItem, 'txItem');
        $itemImages         = $itemCode ? $this->getImageArticle($itemCode) : [];
        $mainItemImage      = $itemImages[0] ?? null;
        $viewData           = [
            'requestData'   => $data,
            'spParams'      => $params,
            'selectedItem'  => $selectedItem,
            'originItems'   => $originItems,
            'rawResponse'   => $info,
            'cliente'       => GpCliente::find($clienteId),
            'articulo'      => GpArticulo::where('ART_CODIGO_TRES_LETRAS', $itemCode)->first(),
            'mainItemImage' => $mainItemImage,
        ];
        $html   = view('pdf.movil.steam_trap_sizing', $viewData)->render();
        $pdf    = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $fileName   = 'dimensionamiento-trampa-' . now()->format('YmdHis') . '-' . Str::random(6) . '.pdf';
        $path       = 'trampas-vapor/' . $fileName;

        Storage::disk('public')->put($path, $pdf->output());
        return asset('storage/' . $path);
    }

    protected function getSteamTrapItems(array $params = [])
    {
        $resp =  $this->api->getTrampasVaporItems($params);
        return data_get($resp, 'data', []);
    }

    protected function getImageArticle(string $codArt): array
    {
        $images = [];

        for ($i = 1; $i <= 3; $i++) {
            $relativePath = "images/productos/{$codArt}_{$i}.jpg";
            $absolutePath = public_path($relativePath);

            if (File::exists($absolutePath)) {
                $images[] = $absolutePath;
            }
        }

        return $images;
    }



    public function generateQuoteDraft(array $payload, GpUsuario $user): mixed
    {
        $clienteId          = data_get($payload, 'customer_id');
        $ruc                = data_get($payload, 'customer_ruc');
        $cliente             = $this->client->getAvailabeCreditCustomer($ruc);
        if (!data_get($cliente, 'cliente')) {
            throw new \RuntimeException('No se encontró información del cliente.');
        }

        $sucursal           = GpClienteSucursal::where('id', $clienteId)->where('estado', true)->where('CLI_SUC_ID_DIRECCION', 'PRINCIPAL')->value('id');
        if (!$sucursal) {
            throw new \RuntimeException('La sucursal seleccionada no existe o no se encuentra activa.');
        }

        $condCode           = data_get($cliente, 'cliente.CLI_CONDICION_PAGO');
        $formPago           = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $condCode)->orderBy('id', 'asc')->value('DESC_FORMA_PAGO_CODIGO');
        if (!$formPago) {
            throw new \RuntimeException('No existen formas de pago relacionadas para la condición de pago del cliente al cual quiere generar el borrador.');
        }

        //bsuqueda de item
        $params             = data_get($payload, 'spParams', []);
        $info               = $this->getSteamTrapItems($params);
        $selectedItem       = data_get($info, 'selectedItem');
        if (empty($selectedItem)) {
            throw new \RuntimeException('No se encontró un artículo recomendado.');
        }
        $artCode  = data_get($selectedItem, 'txItem');
        $articulo = GpArticulo::where('ART_CODIGO_TRES_LETRAS', $artCode)->first();
        $warehouse = $this->getWarehouseByUser($user->id);

        return [
            'cliente'       => $cliente,
            'sucursal'      => $sucursal,
            'metodo_pago'   => $formPago,
            'selectedItem'  => $selectedItem,
            'articulo'      => $articulo,
            'bodega'        => $warehouse,
        ];
    }

    protected function getWarehouseByUser(int $userId)
    {
        return GpRelacionPuntoVentaBodegaRelacionada::query()
            ->with([
                'relacionBodega' => function ($query) {
                    $query->select('id', 'BOD_BODEGA', 'PERMITE_DESPACHO');
                },
            ])
            ->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '<>', 'CONSULTA')
            ->whereHas('puntoVenta.relacionUsuarioPtoVta', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('orden', 1);
            })
            ->orderBy('id')
            ->first();
    }
}
