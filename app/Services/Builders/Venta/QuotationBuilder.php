<?php

namespace App\Services\Builders\Venta;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;

class QuotationBuilder implements QuotationBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function fromPayloadReservedToQuotation(array $data,  array $requiereApproval, GpUsuario $user)
    {

        $cot =  new GpCotizacion();
        $pv =  GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        //normaliza totales
        $subtotalBruto = (float) $data['subtotal'] + (float) $data['descuento'];
        $descuento = (float) $data['descuento'];
        $subMenosDescuento = (float) $data['subtotal'];

        $cot->fill([
            'estado'                    => $requiereApproval['estado'],
            'user_id'                   => $user->id,
            'sucursal_id'               => $data['sucursal_id'],
            'sucursal_facturacion_id'   => $data['sucursal_facturacion_id'],
            'cliente_id'                => $data['cliente_id'],
            'factura_posterior_despacho' => $data['factura_posterior_despacho'],
            'factura_a_demanda'         => $data['factura_a_demanda'],
            'factura_siguiente_mes'     => $data['factura_siguiente_mes'],
            'BOD_BODEGA'                => $data['BOD_BODEGA'],
            'PTO_VTA_ID'                => $data['PTO_VTA_ID'],
            'PTO_VTA_DESCRIPCION'       => $pv->PTO_VTA_DESCRIPCION ?? '',
            'USERID'                    => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO'    => $data['DESC_FORMA_PAGO_CODIGO'],
            'CLI_RUC'                   => $data['CLI_RUC'],
            'CLI_RAZON_SOCIAL'          => $data['CLI_RAZON_SOCIAL'],
            'CLI_CORREO_ELECT_SRI'      => $data['CLI_CORREO_ELECT_SRI'],
            'COND_ID'                   => $data['COND_ID'],
            'COND_ID_ORIGINAL'          => $data['COND_ID_ORIGINAL'],
            'VEND_ID'                   => $data['VEND_ID'],
            'VEND_NOMBRES'              => $data['VEND_NOMBRES'],
            'VEND_APELLIDOS'            => $data['VEND_APELLIDOS'],
            'despacho'                  => $data['despacho'],
            'orden_de_compra'           => $data['orden_de_compra'],
            'porcentage_descuentooriginal' => $data['porcentage_descuentooriginal'],
            'porcentage_descuento'      => $data['porcentage_descuento'],
            'subtotal'                  => $subtotalBruto,
            'descuento'                 => $descuento,
            'subtotalmenosdescuento'    => $subMenosDescuento,
            'iva'                       => (float) $data['iva'],
            'total'                     => (float) $data['total'],
            'requiere_aprobacion_comercial' => $requiereApproval['requiere'],
            'motivo_aprobacion_comercial' => $requiereApproval['motivo'],
            'aprobado_comercial'        => $requiereApproval['aprobado'],
            'aprobador_comercial_id'    => null,
            'requiere_aprobacion_crediticia' => 'no',
            'aprobado_crediticio'       => 'no',
            'aprobador_crediticio_id'   => null,
            'impreso'                   => 'no',
            'nota_interna'              => $data['nota_interna'],
            'nota_cliente'              => $data['nota_cliente'],
            'impuesto' => env('IVA_IMPUESTO'),
        ]);

        return $cot;
    }



    public function formPayloadReservedToDetail($cotizacionId,  $item, $totalLines)
    {
        $det = new GpCotizacionDetalle();
        $det->fill([
            'cotizacion_id'             => $cotizacionId,
            'ART_CODIGO'                => $item['ART_CODIGO'],
            'ART_DESCRIPCION'           => trim(str_replace('  ', ' ', $item['ART_DESCRIPCION'])),
            'ART_DESCRIPCION_ORIGINAL'  => $item['ART_DESCRIPCION_ORIGINAL'],
            'CHECK_ALTERNATIVO'         => $item['CHECK_ALTERNATIVO'] ?? 0,
            'ART_CODIGO_TRES_LETRAS'    => $item['ART_CODIGO_TRES_LETRAS'],
            'ART_DECIMAL_CANTIDAD'      => (int)$item['ART_DECIMAL_CANTIDAD'],
            'ART_PLAN_UMEDIDA'          => $item['ART_PLAN_UMEDIDA'],
            'ART_MARCA'                 => $item['ART_MARCA'],
            'ART_MATERIAL'              => $item['ART_MATERIAL'],
            'ART_PESO'                  => $item['ART_PESO'],
            'ART_TIPO_COMPRA'           => $item['ART_TIPO_COMPRA'],
            'ART_VALOR'                 => (float) $item['ART_VALOR'],
            'ART_VALOR_ORIGINAL'        => (float)$item['ART_VALOR_ORIGINAL'],
            'ART_DESCUENTO'             => (float)$item['ART_DESCUENTO'],
            'ART_IVA'                   => (float)$item['ART_IVA'],
            'ART_CANTIDAD'              => (float)$totalLines,
            'ART_STOCK'                 => (float)$item['ART_STOCK'],
            'ART_IMP_VTA'               => (float)$item['ART_IMP_VTA'],
            'ART_COSTO_PROMEDIO'        => (float)$item['ART_COSTO_PROMEDIO'],
            'descuento'                 => (float)$item['descuento'],
            'descuento_original'        => (float)$item['descuento_original'],
            'modificador'               => (float)$item['modificador'],
        ]);
        return $det;
    }


    public function formPayloadReservedToDetaiLStock($cotizacionId, $detalleId ,$stock, $line)
    {
        $st = new GpCotizacionDetalleStock();
        $st->fill([
            'cotizacion_id' => $cotizacionId,
            'detalle_id' => $detalleId,
            'STO_ART_BODEGA' => $stock['STO_ART_BODEGA'],
            'STO_ART_STOCK_DISPONIBLE' => $stock['STO_ART_STOCK_DISPONIBLE'],
            'seleccionado' => $line['cantidad']
        ]);
        return $st;
    }
}
