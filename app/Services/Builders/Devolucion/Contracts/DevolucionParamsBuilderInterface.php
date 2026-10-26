<?php

namespace App\Services\Builders\Devolucion\Contracts;

use App\Models\Devoluciones\GpDevoluciones;
use App\Models\Devoluciones\GpDevolucionesDetalle;
use App\Models\Devoluciones\GpFacturaAnulada;
use App\Models\Compras\Recepciones\GpIngreso;
use App\Models\Compras\Recepciones\GpIngresosDetalle;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Admin\Usuarios\GpUsuario;

interface DevolucionParamsBuilderInterface
{


    public function formPayloadDevolutionHeader(array $data, array $calculatedData): GpDevoluciones;
    public function formPayloadDevolutionDetail(int $devolucionId, array $details, bool $dispatched): GpDevolucionesDetalle;

    // Define los métodos que debe implementar el servicio
    public function builderParamsDevolutionAdjustment(GpDevoluciones $devolucion, GpPedido $pedido, string $userId, array $items): array;
    public function formPayloadInvoiceAnulation(GpPedido $order, GpDevoluciones $devolucion, ?int $diario, ?int $documentId, string $claveAcceso): GpFacturaAnulada;


    //ingreso 
    public function createRegisterHistortyHeaderDev(GpDevoluciones $dev, GpUsuario $user): GpIngreso;
    public function createRegisterHistoryDetailDev(array $items, GpIngreso $ing): GpIngresosDetalle;

}
