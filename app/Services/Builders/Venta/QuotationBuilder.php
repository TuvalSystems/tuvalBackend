<?php

namespace App\Services\Builders\Venta;

use App\Enums\Ventas\TaxRateEnum;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
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


    public function formPayloadSendStoreReminderGp($quote): array
    {
        return [
            'txCot' => $quote->cotizacion,
            'txUsr' => $quote->usuario->USERID,
            'txCliRuc' => $quote->CLI_RUC,
            'txCliRazSoc' => $quote->CLI_RAZON_SOCIAL,
            'txCotCliSri' => $quote->CLI_CORREO_ELECT_SRI,
            'txEst' => $quote->estado,
            'txBod' => $quote->BOD_BODEGA,
            'txPtoVta' => $quote->PTO_VTA_ID,
            'txPtoVtaDesc' => $quote->PTO_VTA_DESCRIPCION,
            'txCotReqapr' => $quote->requiere_aprobacion_comercial,
            'txCotAprCom' => $quote->aprobado_comercial,
            'txCotMot' => $quote->motivosNoVenta->descripcion,
            'coCotRec' => $quote->recordar,
            'feCotRec' => $quote->fecha_recordatorio,
            'txObsMot' => $quote->observacion,
            'feIng' => date('Y-m-d'),
            'txUsrIng' => $quote->user_ing_recordatorio,
            'feUltmod' => date('Y-m-d'),
            'txUsrMod' => $quote->user_ing_recordatorio,
            'ndCotTot' => $quote->total,
        ];
    }


    public function fromPayloadReservedToQuotation(array $data, array $requiereApproval, GpUsuario $user, $origen)
    {

        $cot = new GpCotizacion();
        $pv = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        //normaliza totales
        $subtotalBruto = (float) $data['subtotal'] + (float) $data['descuento'];
        $descuento = (float) $data['descuento'];
        $subMenosDescuento = (float) $data['subtotal'];

        $cot->fill([
            'estado' => $requiereApproval['estado'],
            'user_id' => $user->id,
            'sucursal_id' => $data['sucursal_id'],
            'sucursal_facturacion_id' => $data['sucursal_facturacion_id'],
            'cliente_id' => $data['cliente_id'],
            'factura_posterior_despacho' => $data['factura_posterior_despacho'],
            'factura_a_demanda' => $data['factura_a_demanda'],
            'factura_siguiente_mes' => $data['factura_siguiente_mes'],
            'BOD_BODEGA' => $data['BOD_BODEGA'],
            'PTO_VTA_ID' => $data['PTO_VTA_ID'],
            'PTO_VTA_DESCRIPCION' => $pv->PTO_VTA_DESCRIPCION ?? '',
            'USERID' => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO' => $data['DESC_FORMA_PAGO_CODIGO'],
            'CLI_RUC' => $data['CLI_RUC'],
            'CLI_RAZON_SOCIAL' => $data['CLI_RAZON_SOCIAL'],
            'CLI_CORREO_ELECT_SRI' => $data['CLI_CORREO_ELECT_SRI'],
            'COND_ID' => $data['COND_ID'],
            'COND_ID_ORIGINAL' => $data['COND_ID_ORIGINAL'],
            'VEND_ID' => $data['VEND_ID'],
            'VEND_NOMBRES' => $data['VEND_NOMBRES'],
            'VEND_APELLIDOS' => $data['VEND_APELLIDOS'],
            'despacho' => $data['despacho'],
            'orden_de_compra' => $data['orden_de_compra'],
            'porcentage_descuentooriginal' => $data['porcentage_descuentooriginal'],
            'porcentage_descuento' => $data['porcentage_descuento'],
            'subtotal' => $subtotalBruto,
            'descuento' => $descuento,
            'subtotalmenosdescuento' => $subMenosDescuento,
            'iva' => (float) $data['iva'],
            'total' => (float) $data['total'],
            'requiere_aprobacion_comercial' => $requiereApproval['requiere'],
            'motivo_aprobacion_comercial' => $requiereApproval['motivo'],
            'aprobado_comercial' => $requiereApproval['aprobado'],
            'aprobador_comercial_id' => null,
            'requiere_aprobacion_crediticia' => 'no',
            'aprobado_crediticio' => 'no',
            'aprobador_crediticio_id' => null,
            'impreso' => 'no',
            'nota_interna' => $data['nota_interna'],
            'nota_cliente' => $data['nota_cliente'],
            'impuesto' => TaxRateEnum::IVA_15->value,
            'origen' => $origen
        ]);

        return $cot;
    }



    public function formPayloadReservedToDetail($cotizacionId, $item, $totalLines)
    {
        $det = new GpCotizacionDetalle();
        $det->fill([
            'cotizacion_id' => $cotizacionId,
            'ART_CODIGO' => $item['ART_CODIGO'],
            'ART_DESCRIPCION' => trim(str_replace('  ', ' ', $item['ART_DESCRIPCION'])),
            'ART_DESCRIPCION_ORIGINAL' => $item['ART_DESCRIPCION_ORIGINAL'],
            'CHECK_ALTERNATIVO' => $item['CHECK_ALTERNATIVO'] ?? 0,
            'ART_CODIGO_TRES_LETRAS' => $item['ART_CODIGO_TRES_LETRAS'],
            'ART_DECIMAL_CANTIDAD' => (int) $item['ART_DECIMAL_CANTIDAD'],
            'ART_PLAN_UMEDIDA' => $item['ART_PLAN_UMEDIDA'],
            'ART_MARCA' => $item['ART_MARCA'],
            'ART_MATERIAL' => $item['ART_MATERIAL'],
            'ART_PESO' => $item['ART_PESO'],
            'ART_TIPO_COMPRA' => $item['ART_TIPO_COMPRA'],
            'ART_VALOR' => (float) $item['ART_VALOR'],
            'ART_VALOR_ORIGINAL' => (float) $item['ART_VALOR_ORIGINAL'],
            'ART_DESCUENTO' => (float) $item['ART_DESCUENTO'],
            'ART_IVA' => (float) $item['ART_IVA'],
            'ART_CANTIDAD' => (float) $totalLines,
            'ART_STOCK' => (float) $item['ART_STOCK'],
            'ART_IMP_VTA' => (float) $item['ART_IMP_VTA'],
            'ART_COSTO_PROMEDIO' => (float) $item['ART_COSTO_PROMEDIO'],
            'descuento' => (float) $item['descuento'],
            'descuento_original' => (float) $item['descuento_original'],
            'modificador' => (float) $item['modificador'],
        ]);
        return $det;
    }


    public function formPayloadReservedToDetaiLStock($cotizacionId, $detalleId, $stock, $line)
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




    public function formPayloadSendClientGp($data, $nivelPrice): array
    {
        $lines = str_split($data['CLI_DIRECCION'], 61);
        $line1 = $lines[0] ?? '';
        $line2 = $lines[1] ?? '';
        return [
            'ruc'                   => $data['CLI_RUC'],
            'razonSocial'           => $data['CLI_RAZON_SOCIAL'],
            'nombreCorto'           => $data['CLI_NOMBRE_CORTO'],
            'clase'                 => 'LOCAL',
            'prioridad'             => '1',
            'contacto'              => $data['CLI_CONTACTO'],
            'direccion1'            => $line1,
            'direccion2'            => $line2,
            'ciudad'                => $data['CLI_CIUDAD'],
            'provincia'             => $data['CLI_PROVINCIA_NOMBRE'],
            'pais'                  => 'Ec',
            'territorio'            => $data['CLI_TERRITORIO'],
            'telefono1'             => $data['CLI_TELEFONO_1'],
            'telefono2'             => $data['CLI_TELEFONO_2'],
            'telefono3'             => $data['CLI_TELEFONO_3'],
            'telefono4'             => $data['CLI_TELEFONO_4'],
            'correoSri'             => $data['CLI_CORREO_ELECT_SRI'],
            'correoNotificacion'    => $data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA'],
            'vendedor'              => $data['CLI_VENDEDOR'],
            'grupo'                 => $data['CLI_GRUPO_NOMBRE'],
            'subGrupo'              => $data['CLI_SUBGRUPO'],
            'descComercial'         => '25',
            'condPago'              => 'CONTADO',
            'nivelPrecio'           => $nivelPrice,
            'puntoVenta'            => $data['CLI_PUNTO_VENTA'],
            'tipoId'                => $data['CLI_TIPO_ID'],
        ];
    }

    public function formPayloadClientWeb(array $data, string $nivelPrecio): array
    {
        $now = now()->toDateTimeString();
        return [
            'CLI_RUC'                               => $data['CLI_RUC'],
            'CLI_RAZON_SOCIAL'                      => $data['CLI_RAZON_SOCIAL'],
            'CLI_NOMBRE_CORTO'                      => $data['CLI_NOMBRE_CORTO'],
            'CLI_CLASE'                             => 'LOCAL',
            'CLI_PRIORIDAD'                         => '1',
            'CLI_ID_DIRECCION'                      => 'PRINCIPAL',
            'CLI_CONTACTO'                          => $data['CLI_CONTACTO'],
            'CLI_DIRECCION'                         => $data['CLI_DIRECCION'],
            'CLI_CIUDAD'                            => $data['CLI_CIUDAD'],
            'CLI_PROVINCIA'                         => $data['CLI_PROVINCIA_NOMBRE'],
            'CLI_TERRITORIO'                        => $data['CLI_TERRITORIO'],
            'CLI_ESTADO'                            => 'ACTIVO',
            'CLI_SUSPENDIDO'                        => 'NO',
            'CLI_TELEFONO_1'                        => $data['CLI_TELEFONO_1'],
            'CLI_TELEFONO_2'                        => $data['CLI_TELEFONO_2'],
            'CLI_TELEFONO_3'                        => $data['CLI_TELEFONO_3'],
            'CLI_TELEFONO_4'                        => $data['CLI_TELEFONO_4'],
            'CLI_FECHA_MODIFICACION'                => $now,
            'CLI_FECHA_CREACION'                    => $now,
            'CLI_CORREO_ELECT_SRI'                  => $data['CLI_CORREO_ELECT_SRI'],
            'CLI_CORREO_ELECT_NOTIFICACION_INTERNA' => $data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA'],
            'CLI_IMPUESTO'                          => TaxRateEnum::IVA_15->value,
            'CLI_DIRECCION_ENVIO'                   => 'PRINCIPAL',
            'CLI_DIRECCION_FACTURA'                 => 'PRINCIPAL',
            'CLI_VENDEDOR'                          => $data['CLI_VENDEDOR'],
            'CLI_DSTO_COMERCIAL'                    => 25,
            'CLI_CONDICION_PAGO'                    => 'CONTADO',
            'CLI_DIA_GRACIA_VTA_CREDITO'            => 0,
            'CLI_DIA_GRACIA_CHEQUE_A_FECHA'         => 0,
            'CLI_NIVEL_PRECIO'                      => $nivelPrecio,
            'CLI_TIPO_CREDITO'                      => 'SIN CREDITO',
            'CLI_MONTO_CREDITO'                     => 0,
            'CLI_GRUPO_ECONOMICO'                   => '',
            'CLI_GRUPO'                             => $data['CLI_GRUPO_NOMBRE'],
            'CLI_SUBGRUPO'                          => $data['CLI_SUBGRUPO'] ?? '',
            'CLI_DIA_MAXIMO_MES_FACTURACION'        => 0,
            'CLI_FACTURA_POSTERIOR_AL_DESPACHO'     => 'NO',
            'CLI_SEGMENTO_ID'                       => $data['CLI_SEGMENTO_ID'] ?? null,
            'CLI_SUBSEGMENTO_ID'                    => $data['CLI_SUBSEGMENTO_ID'] ?? null,
        ];
    }

    public function formPayloadClientSucursal(array $data): array
    {
        return [
            'CLI_SUC_RUC'                                       => $data['CLI_RUC'],
            'CLI_SUC_DESCRIPCION'                               => $data['CLI_RAZON_SOCIAL'],
            'CLI_SUC_ID_DIRECCION'                              => 'PRINCIPAL',
            'CLI_SUC_DIRECCION'                                 => $data['CLI_DIRECCION'],
            'CLI_SUC_CONTACTO'                                  => $data['CLI_CONTACTO'],
            'CLI_SUC_REFERENCIA'                                => $data['CLI_CONTACTO'],
            'CLI_SUC_VENDEDOR'                                  => $data['CLI_VENDEDOR'],
            'CLI_SUC_TERRITORIO'                                => $data['CLI_TERRITORIO'],
            'CLI_SUC_CIUDAD'                                    => $data['CLI_CIUDAD'],
            'CLI_SUC_PROVINCIA'                                 => $data['CLI_PROVINCIA_NOMBRE'],
            'CLI_SUC_PAIS'                                      => 'Ecuador',
            'CLI_SUC_TELEFONO'                                  => $data['CLI_TELEFONO_1'],
            'CLI_SUC_CORREO_ELECTRONICO_SRI'                    => $data['CLI_CORREO_ELECT_SRI'],
            'CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA'   => $data['CLI_CORREO_ELECT_SRI'],
        ];
    }





    /** proces de creacion de cotizacion */

    public function formPayloadHeaderQuotation(GpCotizacion $quotation, array $data, GpUsuario $user, GpPuntoVenta $puntoVenta, array $requiredApprovalCommercial, string $motivoLimpio, string $tipoDespacho): GpCotizacion
    {
        return $quotation->fill([
            'estado'                            => $requiredApprovalCommercial['estado'],
            'cliente_id'                        => data_get($data, 'cliente_id', null),
            'user_id'                           => $user->id,
            'sucursal_id'                       => data_get($data, 'direccion_entrega_id'),
            'sucursal_facturacion_id'           => data_get($data, 'direccion_facturacion_id'),
            'factura_posterior_despacho'        => data_get($data, 'factura_posterior_despacho'),
            'factura_a_demanda'                 => data_get($data, 'factura_a_demanda'),
            'factura_siguiente_mes'             => data_get($data, 'factura_siguiente_mes'),
            'BOD_BODEGA'                        => data_get($data, 'bodega_facturacion'),
            'PTO_VTA_ID'                        => data_get($data, 'punto_venta_id'),
            'PTO_VTA_DESCRIPCION'               => $puntoVenta->PTO_VTA_DESCRIPCION ?? '',
            'USERID'                            => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO'            => data_get($data, 'forma_pago_id'),
            'CLI_RUC'                           => data_get($data, 'ruc'),
            'CLI_RAZON_SOCIAL'                  => data_get($data, 'razon_social'),
            'CLI_CORREO_ELECT_SRI'              => data_get($data, 'correo_electronico_sri'),
            'COND_ID'                           => data_get($data, 'condicion_pago_id'),
            'COND_ID_ORIGINAL'                  => data_get($data, 'condicion_pago_original_id'),
            'VEND_ID'                           => data_get($data, 'vendedor_id'),
            'VEND_NOMBRES'                      => data_get($data, 'vendedor_nombres'),
            'VEND_APELLIDOS'                    => data_get($data, 'vendedor_apellidos'),
            'despacho'                          => $tipoDespacho,
            'orden_de_compra'                   => data_get($data, 'orden_de_compra'),
            'porcentage_descuento'              => data_get($data, 'descuento_articulo'),
            'porcentage_descuentooriginal'      => data_get($data, 'descuento_original'),
            'impuesto'                          => TaxRateEnum::IVA_15->value,
            'subtotal'                          => (float) data_get($data, 'subtotal') + (float) data_get($data, 'descuento'),
            'descuento'                         => (float) data_get($data, 'descuento'),
            'subtotalmenosdescuento'            => (float) data_get($data, 'subtotal'),
            'iva'                               => (float) data_get($data, 'iva'),
            'total'                             => (float) data_get($data, 'total'),
            'requiere_aprobacion_comercial'     => data_get($requiredApprovalCommercial, 'requiere'),
            'motivo_aprobacion_comercial'       => $motivoLimpio,
            'aprobado_comercial'                => data_get($requiredApprovalCommercial, 'aprobado'),
            'aprobador_comercial_id'            => null,
            'requiere_aprobacion_crediticia'    => 'no',
            'aprobado_crediticio'               => 'no',
            'aprobador_crediticio_id'           => null,
            'impreso'                           => 'no',
            'nota_interna'                      => data_get($data, 'nota_interna'),
            'nota_cliente'                      => data_get($data, 'nota_cliente'),
            'nota_operaciones'                  => data_get($data, 'nota_operaciones'),
            'origen'                            => 'cotizacion',
            'modificador_global'                => (float) data_get($data, 'modificador_global', 0),
            'fecha_entrega'                     => data_get($data, 'fecha_entrega', null),

        ]);
    }

    public function formPayloadDetailQuotation(int $quotationId, array $articles): GpCotizacionDetalle
    {
        $qd = new GpCotizacionDetalle();
        $qd->fill([
            'cotizacion_id'                 => $quotationId,
            'ART_CODIGO'                    => data_get($articles, 'ART_CODIGO'),
            'ART_DESCRIPCION'               => data_get($articles, 'ART_DESCRIPCION'),
            'ART_DESCRIPCION_ORIGINAL'      => data_get($articles, 'ART_DESCRIPCION_ORIGINAL'),
            'CHECK_ALTERNATIVO'             => data_get($articles, 'CHECK_ALTERNATIVO', 0),
            'ART_CODIGO_TRES_LETRAS'        => data_get($articles, 'ART_CODIGO_TRES_LETRAS'),
            'ART_DECIMAL_CANTIDAD'          => data_get($articles, 'ART_DECIMAL_CANTIDAD'),
            'ART_PLAN_UMEDIDA'              => data_get($articles, 'ART_PLAN_UMEDIDA'),
            'ART_MARCA'                     => data_get($articles, 'ART_MARCA'),
            'ART_MATERIAL'                  => data_get($articles, 'ART_MATERIAL'),
            'ART_PESO'                      => data_get($articles, 'ART_PESO'),
            'ART_TIPO_COMPRA'               => data_get($articles, 'ART_TIPO_COMPRA'),
            'ART_VALOR'                     => data_get($articles, 'ART_VALOR'),
            'ART_VALOR_ORIGINAL'            => data_get($articles, 'ART_VALOR_ORIGINAL'),
            'ART_DESCUENTO'                 => data_get($articles, 'ART_DESCUENTO'),
            'ART_IVA'                       => data_get($articles, 'ART_IVA'),
            'ART_CANTIDAD'                  => data_get($articles, 'ART_CANTIDAD'),
            'ART_STOCK'                     => data_get($articles, 'ART_STOCK'),
            'ART_IMP_VTA'                   => data_get($articles, 'ART_IMP_VTA'),
            'ART_COSTO_PROMEDIO'            => data_get($articles, 'ART_COSTO_PROMEDIO'),
            'descuento'                     => data_get($articles, 'descuento'),
            'descuento_original'            => data_get($articles, 'descuento_original'),
            'modificador'                   => data_get($articles, 'modificador'),
        ]);

        return $qd;
    }


    public function formPayloadProvidersDetailQuotation(int $quoteId, int $detailId, array $provider): GpCotizacionDetalleProveedores
    {
        $prov = new GpCotizacionDetalleProveedores();
        $prov->fill([
            'cotizacion_id'         => $quoteId,
            'detalle_id'            => $detailId,
            'ID_PROVEEDOR'          => data_get($provider, 'ID_PROVEEDOR'),
            'DESCRIPCION_ARTICULO'  => data_get($provider, 'DESCRIPCION_ARTICULO'),
            'FECHA_ENTREGA'         => data_get($provider, 'FECHA_ENTREGA'),
            'CANTIDAD'              => data_get($provider, 'CANTIDAD'),
            'COSTO_UNITARIO'        => data_get($provider, 'COSTO_UNITARIO'),
            'IMPUESTO'              => data_get($provider, 'IMPUESTO'),
            'TOTAL'                 => data_get($provider, 'TOTAL'),
            'TOTALCONIMPUESTO'      => data_get($provider, 'TOTALCONIMPUESTO'),
            'NOTA'                  => data_get($provider, 'NOTA'),
            'MARGEN'                => data_get($provider, 'MARGEN', 0)
        ]);

        return $prov;
    }


    public function formPayloadStocksDetailQuotation(int $quoteId, int $detailId, array $stock): GpCotizacionDetalleStock
    {
        $st = new GpCotizacionDetalleStock();
        $st->fill([
            'cotizacion_id'                 => $quoteId,
            'detalle_id'                    => $detailId,
            'STO_ART_BODEGA'                => data_get($stock, 'STO_ART_BODEGA'),
            'STO_ART_STOCK_DISPONIBLE'      => data_get($stock, 'STO_ART_STOCK_DISPONIBLE'),
            'seleccionado'                  => data_get($stock, 'seleccionado')
        ]);
        return $st;
    }
}
