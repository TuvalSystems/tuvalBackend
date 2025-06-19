<?php

namespace App\Services\Venta;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionVentaMotivo;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Services\Api\ApiGpService;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class QuotationService implements QuotationServiceInterface
{
    protected $quotationRepository;
    protected $apiGpService;
    public function __construct(QuotationRepositoryInterface $quotationRepository, ApiGpService $apiGpService)
    {
        $this->quotationRepository = $quotationRepository;
        $this->apiGpService = $apiGpService;
    }


    public function getNonSalesReasons(): Collection
    {
        return GpCotizacionVentaMotivo::orderBy('id')->get();
    }

    public function updateNonSaleAndReminderData($data)
    {
        $user = Auth::user();
        $cotizacion = GpCotizacion::where('id', $data['cotizacion_id'])->update([
            'fecha_recordatorio' => $data['fecha_recordatorio'],
            'cotizacion_venta_motivo_id' => $data['motivo_id'],
            'observacion' => $data['observacion'],
            'recordar' => $data['recordar'],
            'user_ing_recordatorio' => $user->USERID,

        ]);
        $sendQuote = GpCotizacion::with(['usuario', 'usuariorecordatorio','motivosNoVenta'])->find($data['cotizacion_id']);
        $this->sendStoreReminderGp($sendQuote);
        return $cotizacion;
    }

    public function sendStoreReminderGp($cotizacion)
    {
        $parmas = [
            'txCot' => $cotizacion->cotizacion,
            'txUsr' => $cotizacion->usuario->USERID,
            'txCliRuc' =>  $cotizacion->CLI_RUC,
            'txCliRazSoc' => $cotizacion->CLI_RAZON_SOCIAL,
            'txCotCliSri' => $cotizacion->CLI_CORREO_ELECT_SRI,
            'txEst' => $cotizacion->estado,
            'txBod' => $cotizacion->BOD_BODEGA,
            'txPtoVta' =>  $cotizacion->PTO_VTA_ID,
            'txPtoVtaDesc'=> $cotizacion->PTO_VTA_DESCRIPCION,
            'txCotReqapr' => $cotizacion->requiere_aprobacion_comercial,
            'txCotAprCom' => $cotizacion->aprobado_comercial,
            'txCotMot' => $cotizacion->motivosNoVenta->descripcion,
            'coCotRec' => $cotizacion->recordar,
            'feCotRec' => $cotizacion->fecha_recordatorio,
            'txObsMot' => $cotizacion->observacion,
            'feIng' =>  date('Y-m-d'),
            'txUsrIng' => $cotizacion->user_ing_recordatorio,
            'feUltmod' => date('Y-m-d'),
            'txUsrMod' =>   $cotizacion->user_ing_recordatorio
        ];
        $this->apiGpService->storeQuoteReminderGp($parmas);

    }



    //proceso job

    public function getQuotationsWithReminderToday()
    {
        return $this->quotationRepository->getScheduledReminders();
    }


    /***
     * RECUPERAR COTIZACION
     */

    public function restoreQuotation($articulos, $bodegas)
    {
        $formatStock = [];
        foreach ($articulos as $value) {
            $stockRequests = array_map(fn($whr) => ['articulo' => $value['articulo'], 'bodega' => $whr,], $bodegas);
            $stockResults = array_map(fn($params) => $this->apiGpService->getStockWarehouse($params), $stockRequests);
            $formatted = $this->formatResultStocksQuotation($stockResults);
            $formatStock = array_merge($formatStock, $formatted);
        }

        return $formatStock;

    }


    public function formatResultStocksQuotation($stockResults)
    {
        if (empty($stockResults)) {
            return [];
        }
        $formattedStocks = [];
        foreach ($stockResults as $value) {
            if (!is_array($value)) {
                continue;
            }
            $formattedStocks = array_merge(
                $formattedStocks,
                array_map(
                    fn($item) => [
                        'STO_ART_ARTICULO' => $item['ARTICULO'] ?? '',
                        'STO_ART_BODEGA' => $item['BODEGA'] ?? '',
                        'STO_ART_STOCK_DISPONIBLE' => number_format((float) $item['STOCK_DISPONIBLE'], 2, '.', '') ?? 0,
                    ],
                    array_values($value)
                )
            );
        }
        return $formattedStocks;
    }

}
