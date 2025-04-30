<?php

namespace App\Services\Pedidos;

use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpVendedor;
use App\Models\GpPuntoVenta;
use App\Models\GpPedidoDetalle;
use App\Models\GpClienteSucursal;
use App\Services\Api\ApiGpService;
use App\Models\GpDescuentoFormaPago;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\File;
use App\Services\Api\ApiSalesService;
use App\Http\Controllers\WebserviceController;
use Carbon\Carbon;
use Milon\Barcode\DNS1D;

class OrderService implements OrderServiceInterface
{
    use JsonResponseTrait;
    protected ApiSalesService $apiSalesService;
    protected ApiGpService $apiGpService;

    public function __construct(ApiSalesService $apiSalesService, ApiGpService $apiGpService)
    {
        $this->apiGpService = $apiGpService;
        $this->apiSalesService = $apiSalesService;
    }

    //me obtiene la informacion del documento xml_notificaciones



    public function getOrderInfo($id)
    {

        $pedido = $this->getOrderInfoById($id);

        if (!$pedido || $this->isPedidoBlocked($pedido)) {
            return 'No permitido';
        }

        $detalleData = $this->buildOrderDetail($pedido);
        $pedido['detalle'] = $detalleData['detalle'];
        $pedido['peso'] = $detalleData['peso'];
        $pedido['tipoimpuesto'] = $detalleData['tipoImpuesto'];
        $pedido['sumaimpuesto'] = $detalleData['sumaImpuesto'];
        $this->appendPagoAndClienteInfo($pedido);
        return $pedido;
    }

    public function getOrderInfoById($id)
    {
        return GpPedido::leftJoin('gp_cotizacions', 'gp_cotizacions.id', 'gp_pedidos.cotizacion_id')
            ->join('gp_usuarios', 'gp_usuarios.USERID', 'gp_pedidos.USERID')
            ->select('gp_pedidos.*', 'gp_cotizacions.cotizacion', 'gp_usuarios.USERNAME')
            ->where('gp_pedidos.id', $id)
            ->first()
            ?->toArray();
    }
    public function isPedidoBlocked(array $pedido): bool
    {
        return ($pedido['requiere_aprobacion_comercial'] === 'yes' && $pedido['aprobado_comercial'] === 'no') ||
            ($pedido['requiere_aprobacion_crediticia'] === 'yes' && $pedido['aprobado_crediticio'] === 'no') ||
            $pedido['estado'] === 'eliminado';
    }

    public function buildOrderDetail(array $pedido)
    {
        $detalle = GpPedidoDetalle::where('pedido_id', $pedido['id'])->get()->toarray();
        $peso = 0;
        $tipoImpuesto = [];
        $sumaImpuesto = [];

        foreach ($detalle as &$item) {

            $pesoItem = $item['ART_PESO'] * $item['ART_CANTIDAD'];
            $item['ART_PESO_TOTAL'] = number_format($pesoItem, 2, '.', '');
            $peso += $pesoItem;
            $item['compartimiento'] = $this->getCompartimento($item['ART_CODIGO'], $pedido['BOD_BODEGA']);
            $impKey = $item['ART_IMP_VTA'];
            $tipoImpuesto[$impKey] = ($tipoImpuesto[$impKey] ?? 0) + round($item['ART_VALOR'] * $item['ART_CANTIDAD'], 2);
            $sumaImpuesto[$impKey] = ($sumaImpuesto[$impKey] ?? 0) + round($item['ART_IVA'], 2);
            $item['imagenes'] = $this->getImagenes($item['ART_CODIGO_TRES_LETRAS']);
        }
        return compact('detalle', 'peso', 'tipoImpuesto', 'sumaImpuesto');
    }

    public function getCompartimento($articulo, $bodega)
    {
        $params = array(
            'articulo' => $articulo,
            'bodega' => $bodega,
            'compartimento' => '',
        );

        $compartment = $this->apiGpService->getStockByWarehouseAndCompartment($params);
        if (isset($compartment) && count($compartment) > 0 && isset($compartment[0]['COMPARTIMIENTO'])) {
            $stockDisponible = array_map('floatval', array_column($compartment, 'STOCK_DISPONIBLE'));
            array_multisort($stockDisponible, SORT_DESC, $compartment);

            return $compartment[0]['COMPARTIMIENTO'];
        }
        return null;
    }

    public function getImagenes(string $codigo): array
    {
        $imagenes = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = "images/productos/{$codigo}_{$i}.jpg";
            if (File::exists($path)) {
                $imagenes[] = $path;
            }
        }
        return $imagenes;
    }

    public function appendPagoAndClienteInfo(&$pedido)
    {
        $formaPago  = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $pedido['DESC_FORMA_PAGO_CODIGO'])->first()->toarray();
        $pedido['formadepago'] = $formaPago['DESC_FORMA_PAGO_TIPO_CREDITO'] ?? null;
        $pedido['formadepagoSRI'] = substr($formaPago['DESC_FORMA_PAGO_SRI'] ?? '', 3);

        $cliente = GpCliente::find($pedido['cliente_id'])?->toArray();
        if ($cliente) {
            $emails = explode(';', str_replace(',', ';', $cliente['CLI_CORREO_ELECT_SRI'] ?? ''));
            $cliente['CLI_CORREO_ELECT_SRI'] = $emails[0] ?? '';
            $pedido['cliente'] = $cliente;
        }

        $sucursal = GpClienteSucursal::find($pedido['sucursal_facturacion_id'])?->toArray();
        $pedido['sucursal'] = $sucursal;
        $pedido['vendedor'] = GpVendedor::where('VEND_ID', $sucursal['CLI_SUC_VENDEDOR'])->first()->toarray();
        $ptoVenta = GpPuntoVenta::find($pedido['PTO_VTA_ID'])?->toArray();
        $pedido['direccion'] = trim(($ptoVenta['PTO_VTA_DIRECCION_1'] ?? '') . ' ' . ($ptoVenta['PTO_VTA_DIRECCION_2'] ?? ''));
        $pedido['telefono'] = $ptoVenta['PTO_VTA_FONO_1'] ?? '';
    }



    public function generateInvoiceRide($id)
    {
        $pedido = $this->getOrderInfo($id);
        $datosFactura = $this->getInvoiceDocumentInfo($pedido);
        if ($datosFactura['success'] === false) {
            throw new \Exception($datosFactura['message']);
        }
        $pedido['clave_acceso'] = $datosFactura['data']['claveAcceso'];
        $pedido['fecha_autorizacion'] = Carbon::parse($datosFactura['data']['fechaEnvio'])->format('Y-m-d H:i:s');
        return $pedido;
    }

    public function getInvoiceDocumentInfo($pedido)
    {
        return $this->apiSalesService->getDataDocumentGp(array(
            'numdoc' => $pedido['factura'],
        ));
    }

    public function generateInvoiceXml($id){
        $pedido = $this->getOrderInfo($id);
        $datosFactura = $this->getInvoiceDocumentInfo($pedido);
        if ($datosFactura['success'] === false) {
           throw new \Exception($datosFactura['message']);
        }
        $datos['xml'] = $datosFactura['data']['xml'];
        $datos['factura'] = $pedido['factura'];
        return $datos;
    }
}
