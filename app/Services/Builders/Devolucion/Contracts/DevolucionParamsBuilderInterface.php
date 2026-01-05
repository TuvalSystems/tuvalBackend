<?php

namespace App\Services\Builders\Devolucion\Contracts;

use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Models\GpPedido;
use App\Models\GpUsuario;

interface DevolucionParamsBuilderInterface
{


    public function formPayloadDevolutionHeader(array $data, array $calculatedData): GpDevoluciones;
    public function formPayloadDevolutionDetail(int $devolucionId, array $details, bool $dispatched): GpDevolucionesDetalle;

    // Define los métodos que debe implementar el servicio
    public function builderParamsDevolutionAdjustment(GpDevoluciones $devolucion, GpPedido $pedido, $userId, $items);
    public function formPayloadInvoiceAnulation($order, $devolucion, $diario, $documentId, $claveAcceso): GpFacturaAnulada;


    //ingreso 
    public function createRegisterHistortyHeaderDev($dev, GpUsuario $user);
    public function createRegisterHistoryDetailDev($items, $ing);

}
