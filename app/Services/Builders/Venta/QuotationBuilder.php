<?php

namespace App\Services\Builders\Venta;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Enums\Ventas\TaxRateEnum;
use App\Models\Articulos\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Models\Ventas\Cotizaciones\HiCotizacion;
use App\Models\Ventas\Cotizaciones\HiCotizacionDetalle;
use App\Models\Ventas\Cotizaciones\HiCotizacionDetalleProveedores;
use App\Models\Ventas\Cotizaciones\HiCotizacionDetalleStock;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Traits\CleansPayloadData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class QuotationBuilder implements QuotationBuilderInterface
{
    use CleansPayloadData;
    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function formPayloadSendStoreReminderGp($quote): array
    {
        return [
            'txCot'             => $quote->cotizacion,
            'txUsr'             => $quote->usuario->USERID,
            'txCliRuc'          => $quote->CLI_RUC,
            'txCliRazSoc'       => $quote->CLI_RAZON_SOCIAL,
            'txCotCliSri'       => $quote->CLI_CORREO_ELECT_SRI,
            'txEst'             => $quote->estado,
            'txBod'             => $quote->BOD_BODEGA,
            'txPtoVta'          => $quote->PTO_VTA_ID,
            'txPtoVtaDesc'      => $quote->PTO_VTA_DESCRIPCION,
            'txCotReqapr'       => $quote->requiere_aprobacion_comercial,
            'txCotAprCom'       => $quote->aprobado_comercial,
            'txCotMot'          => $quote->motivosNoVenta->descripcion,
            'coCotRec'          => $quote->recordar,
            'feCotRec'          => $quote->fecha_recordatorio,
            'txObsMot'          => $quote->observacion,
            'feIng'             => date('Y-m-d'),
            'txUsrIng'          => $quote->user_ing_recordatorio,
            'feUltmod'          => date('Y-m-d'),
            'txUsrMod'          => $quote->user_ing_recordatorio,
            'ndCotTot'          => $quote->total,
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
            'estado'                            => $requiereApproval['estado'],
            'user_id'                           => $user->id,
            'sucursal_id'                       => $data['sucursal_id'],
            'sucursal_facturacion_id'           => $data['sucursal_facturacion_id'],
            'cliente_id'                        => $data['cliente_id'],
            'factura_posterior_despacho'        => $data['factura_posterior_despacho'],
            'factura_a_demanda'                 => $data['factura_a_demanda'],
            'factura_siguiente_mes'             => $data['factura_siguiente_mes'],
            'BOD_BODEGA'                        => $data['BOD_BODEGA'],
            'PTO_VTA_ID'                        => $data['PTO_VTA_ID'],
            'PTO_VTA_DESCRIPCION'               => $pv->PTO_VTA_DESCRIPCION ?? '',
            'USERID'                            => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO'            => $data['DESC_FORMA_PAGO_CODIGO'],
            'CLI_RUC'                           => $data['CLI_RUC'],
            'CLI_RAZON_SOCIAL'                  => $data['CLI_RAZON_SOCIAL'],
            'CLI_CORREO_ELECT_SRI'              => $data['CLI_CORREO_ELECT_SRI'],
            'COND_ID'                           => $data['COND_ID'],
            'COND_ID_ORIGINAL'                  => $data['COND_ID_ORIGINAL'],
            'VEND_ID'                           => $data['VEND_ID'],
            'VEND_NOMBRES'                      => $data['VEND_NOMBRES'],
            'VEND_APELLIDOS'                    => $data['VEND_APELLIDOS'],
            'despacho'                          => $data['despacho'],
            'orden_de_compra'                   => $data['orden_de_compra'],
            'porcentage_descuentooriginal'      => $data['porcentage_descuentooriginal'],
            'porcentage_descuento'              => $data['porcentage_descuento'],
            'subtotal'                          => $subtotalBruto,
            'descuento'                         => $descuento,
            'subtotalmenosdescuento'            => $subMenosDescuento,
            'iva'                               => (float) $data['iva'],
            'total'                             => (float) $data['total'],
            'requiere_aprobacion_comercial'     => $requiereApproval['requiere'],
            'motivo_aprobacion_comercial'       => $requiereApproval['motivo'],
            'aprobado_comercial'                => $requiereApproval['aprobado'],
            'aprobador_comercial_id'            => null,
            'requiere_aprobacion_crediticia'    => 'no',
            'aprobado_crediticio'               => 'no',
            'aprobador_crediticio_id'           => null,
            'impreso'                           => 'no',
            'nota_interna'                      => $data['nota_interna'],
            'nota_cliente'                      => $data['nota_cliente'],
            'nota_operaciones'                  => data_get($data, 'nota_operaciones', ''),
            'impuesto'                          => TaxRateEnum::IVA_15->value,
            'origen'                            => $origen,
            'modificador_global'                => (float) data_get($data, 'modificador_global', 0),
        ]);
        return $cot;
    }



    public function formPayloadReservedToDetail($cotizacionId, $item, $totalLines)
    {
        $det = new GpCotizacionDetalle();
        $det->fill([
            'cotizacion_id'                 => $cotizacionId,
            'ART_CODIGO'                    => $item['ART_CODIGO'],
            'ART_DESCRIPCION'               => trim(str_replace('  ', ' ', $item['ART_DESCRIPCION'])),
            'ART_DESCRIPCION_ORIGINAL'      => $item['ART_DESCRIPCION_ORIGINAL'],
            'CHECK_ALTERNATIVO'             => $item['CHECK_ALTERNATIVO'] ?? 0,
            'ART_CODIGO_TRES_LETRAS'        => $item['ART_CODIGO_TRES_LETRAS'],
            'ART_DECIMAL_CANTIDAD'          => (int) $item['ART_DECIMAL_CANTIDAD'],
            'ART_PLAN_UMEDIDA'              => $item['ART_PLAN_UMEDIDA'],
            'ART_MARCA'                     => $item['ART_MARCA'],
            'ART_MATERIAL'                  => $item['ART_MATERIAL'],
            'ART_PESO'                      => $item['ART_PESO'],
            'ART_TIPO_COMPRA'               => $item['ART_TIPO_COMPRA'],
            'ART_VALOR'                     => (float) $item['ART_VALOR'],
            'ART_VALOR_ORIGINAL'            => (float) $item['ART_VALOR_ORIGINAL'],
            'ART_DESCUENTO'                 => (float) $item['ART_DESCUENTO'],
            'ART_IVA'                       => (float) $item['ART_IVA'],
            'ART_CANTIDAD'                  => (float) $totalLines,
            'ART_STOCK'                     => (float) $item['ART_STOCK'],
            'ART_IMP_VTA'                   => (float) $item['ART_IMP_VTA'],
            'ART_COSTO_PROMEDIO'            => (float) $item['ART_COSTO_PROMEDIO'],
            'descuento'                     => (float) $item['descuento'],
            'descuento_original'            => (float) $item['descuento_original'],
            'modificador'                   => (float) $item['modificador'],
        ]);
        return $det;
    }


    public function formPayloadReservedToDetaiLStock($cotizacionId, $detalleId, $stock, $line)
    {
        $st = new GpCotizacionDetalleStock();
        $st->fill([
            'cotizacion_id'             => $cotizacionId,
            'detalle_id'                => $detalleId,
            'STO_ART_BODEGA'            => $stock['STO_ART_BODEGA'],
            'STO_ART_STOCK_DISPONIBLE'  => $stock['STO_ART_STOCK_DISPONIBLE'],
            'seleccionado'              => $line['cantidad']
        ]);
        return $st;
    }




    public function formPayloadSendClientGp($data, $nivelPrice): array
    {
        $lines = str_split($data['CLI_DIRECCION'], 61);
        $line1 = $lines[0] ?? '';
        $line2 = $lines[1] ?? '';
        return [
            'ruc'                   => $this->clean($data, 'CLI_RUC'),
            'razonSocial'           => $this->clean($data, 'CLI_RAZON_SOCIAL'),
            'nombreCorto'           => $this->clean($data, 'CLI_NOMBRE_CORTO'),
            'clase'                 => 'LOCAL',
            'prioridad'             => '1',
            'contacto'              =>  $this->clean($data, 'CLI_CONTACTO'),
            'direccion1'            => trim($line1),
            'direccion2'            => trim($line2),
            'ciudad'                => $this->clean($data, 'CLI_CIUDAD'),
            'provincia'             => $this->clean($data, 'CLI_PROVINCIA_NOMBRE'),
            'pais'                  => 'Ec',
            'territorio'            => $this->clean($data, 'CLI_TERRITORIO'),
            'telefono1'             => $this->clean($data, 'CLI_TELEFONO_1'),
            'telefono2'             => $this->clean($data, 'CLI_TELEFONO_2'),
            'telefono3'             => $this->clean($data, 'CLI_TELEFONO_3'),
            'telefono4'             => $this->clean($data, 'CLI_TELEFONO_4'),
            'correoSri'             => $this->cleanLower($data, 'CLI_CORREO_ELECT_SRI'),
            'correoNotificacion'    => $this->cleanLower($data, 'CLI_CORREO_ELECT_NOTIFICACION_INTERNA'),
            'vendedor'              => $this->clean($data, 'CLI_VENDEDOR'),
            'grupo'                 => $this->clean($data, 'CLI_GRUPO_NOMBRE'),
            'subGrupo'              => $this->clean($data, 'CLI_SUBGRUPO'),
            'descComercial'         => '25',
            'condPago'              => 'CONTADO',
            'nivelPrecio'           => $nivelPrice,
            'puntoVenta'            => $this->clean($data, 'CLI_PUNTO_VENTA'),
            'tipoId'                => $this->clean($data, 'CLI_TIPO_ID'),
        ];
    }

    public function formPayloadClientWeb(array $data, string $nivelPrecio,  GpUsuario $user): array
    {
        $now = now()->toDateTimeString();
        return [
            'tipo_identificacion'                   => $this->clean($data, 'CLI_TIPO_ID'),
            'CLI_RUC'                               => $this->clean($data, 'CLI_RUC'),
            'CLI_RAZON_SOCIAL'                      => $this->clean($data, 'CLI_RAZON_SOCIAL'),
            'CLI_NOMBRE_CORTO'                      => $this->clean($data, 'CLI_NOMBRE_CORTO'),
            'CLI_CLASE'                             => 'LOCAL',
            'CLI_PRIORIDAD'                         => '1',
            'CLI_ID_DIRECCION'                      => 'PRINCIPAL',
            'CLI_CONTACTO'                          => $this->clean($data, 'CLI_CONTACTO'),
            'CLI_DIRECCION'                         => $this->clean($data, 'CLI_DIRECCION'),
            'CLI_CIUDAD'                            => $this->clean($data, 'CLI_CIUDAD'),
            'CLI_PROVINCIA'                         => $this->clean($data, 'CLI_PROVINCIA_NOMBRE'),
            'CLI_TERRITORIO'                        => $this->clean($data, 'CLI_TERRITORIO'),
            'CLI_ESTADO'                            => 'ACTIVO',
            'CLI_SUSPENDIDO'                        => 'NO',
            'CLI_TELEFONO_1'                        => $this->clean($data, 'CLI_TELEFONO_1'),
            'CLI_TELEFONO_2'                        => $this->clean($data, 'CLI_TELEFONO_2'),
            'CLI_TELEFONO_3'                        => $this->clean($data, 'CLI_TELEFONO_3'),
            'CLI_TELEFONO_4'                        => $this->clean($data, 'CLI_TELEFONO_4'),
            'CLI_FECHA_MODIFICACION'                => $now,
            'CLI_FECHA_CREACION'                    => $now,
            'CLI_CORREO_ELECT_SRI'                  => $this->cleanLower($data, 'CLI_CORREO_ELECT_SRI'),
            'CLI_CORREO_ELECT_NOTIFICACION_INTERNA' => $this->cleanLower($data, 'CLI_CORREO_ELECT_NOTIFICACION_INTERNA'),
            'CLI_IMPUESTO'                          => TaxRateEnum::IVA_15->value,
            'CLI_DIRECCION_ENVIO'                   => 'PRINCIPAL',
            'CLI_DIRECCION_FACTURA'                 => 'PRINCIPAL',
            'CLI_VENDEDOR'                          => $this->clean($data, 'CLI_VENDEDOR'),
            'CLI_DSTO_COMERCIAL'                    => 25,
            'CLI_CONDICION_PAGO'                    => 'CONTADO',
            'CLI_DIA_GRACIA_VTA_CREDITO'            => 0,
            'CLI_DIA_GRACIA_CHEQUE_A_FECHA'         => 0,
            'CLI_NIVEL_PRECIO'                      => $nivelPrecio,
            'CLI_TIPO_CREDITO'                      => 'SIN CREDITO',
            'CLI_MONTO_CREDITO'                     => 0,
            'CLI_GRUPO_ECONOMICO'                   => '',
            'CLI_GRUPO'                             => $this->clean($data, 'CLI_GRUPO_NOMBRE'),
            'CLI_SUBGRUPO'                          => $this->clean($data, 'CLI_SUBGRUPO', ''),
            'CLI_DIA_MAXIMO_MES_FACTURACION'        => 0,
            'CLI_FACTURA_POSTERIOR_AL_DESPACHO'     => 'NO',
            'CLI_SEGMENTO_ID'                       => $this->cleanNullable($data, 'CLI_SEGMENTO_ID'),
            'CLI_SUBSEGMENTO_ID'                    => $this->cleanNullable($data, 'CLI_SUBSEGMENTO_ID'),
            'user_creator_id'                       => $user->id,
            'fecha_hora_creacion'                   => Carbon::now()->toDateTimeString(),

        ];
    }

    public function formPayloadClientSucursal(array $data, int $clientId, GpUsuario $user): array
    {
        return [
            'cliente_id'                                        => $clientId,
            'CLI_SUC_RUC'                                       => $this->clean($data, 'CLI_RUC'),
            'CLI_SUC_DESCRIPCION'                               => $this->clean($data, 'CLI_RAZON_SOCIAL'),
            'CLI_SUC_ID_DIRECCION'                              => 'PRINCIPAL',
            'CLI_SUC_DIRECCION'                                 => $this->clean($data, 'CLI_DIRECCION'),
            'CLI_SUC_CONTACTO'                                  => $this->clean($data, 'CLI_CONTACTO'),
            'CLI_SUC_REFERENCIA'                                => $this->clean($data, 'CLI_CONTACTO'),
            'CLI_SUC_VENDEDOR'                                  => $this->clean($data, 'CLI_VENDEDOR'),
            'CLI_SUC_TERRITORIO'                                => $this->clean($data, 'CLI_TERRITORIO'),
            'CLI_SUC_CIUDAD'                                    => $this->clean($data, 'CLI_CIUDAD'),
            'CLI_SUC_PROVINCIA'                                 => $this->clean($data, 'CLI_PROVINCIA_NOMBRE'),
            'CLI_SUC_PAIS'                                      => 'Ecuador',
            'CLI_SUC_TELEFONO'                                  => $this->clean($data, 'CLI_TELEFONO_1'),
            'CLI_SUC_CORREO_ELECTRONICO_SRI'                    => $this->cleanLower($data, 'CLI_CORREO_ELECT_SRI'),
            'CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA'   => $this->cleanLower($data, 'CLI_CORREO_ELECT_SRI'),
            'user_creator_id'                                   => $user->id,
            'fecha_hora_creacion'                               => Carbon::now()->toDateTimeString(),
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
            'DESC_FORMA_PAGO_CODIGO'            => data_get($data, 'forma_pago_id', null),
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
            'nota_interna'                      => data_get($data, 'nota_interna', null),
            'nota_cliente'                      => data_get($data, 'nota_cliente', null),
            'nota_operaciones'                  => data_get($data, 'nota_operaciones', null),
            'origen'                            => data_get($data, 'origen', 'cotizacion'),
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
            'ART_VALOR'                     => (float)data_get($articles, 'ART_VALOR'),
            'ART_VALOR_ORIGINAL'            => (float)data_get($articles, 'ART_VALOR_ORIGINAL'),
            'ART_DESCUENTO'                 => (float)data_get($articles, 'ART_DESCUENTO'),
            'ART_IVA'                       => (float)data_get($articles, 'ART_IVA'),
            'ART_CANTIDAD'                  => (float) round(data_get($articles, 'ART_CANTIDAD'), (int)data_get($articles, 'ART_DECIMAL_CANTIDAD')),
            'ART_STOCK'                     => data_get($articles, 'ART_STOCK', 0),
            'ART_IMP_VTA'                   => (float)data_get($articles, 'ART_IMP_VTA'),
            'ART_COSTO_PROMEDIO'            => (float)data_get($articles, 'ART_COSTO_PROMEDIO'),
            'descuento'                     => (float)data_get($articles, 'descuento'),
            'descuento_original'            => (float)data_get($articles, 'descuento_original'),
            'modificador'                   => (float)data_get($articles, 'modificador'),
            'valor_original_l'              => (float)data_get($articles, 'valor_original_l', 0),
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
            'COSTO_UNITARIO'        => (float)data_get($provider, 'COSTO_UNITARIO'),
            'IMPUESTO'              => (float)data_get($provider, 'IMPUESTO'),
            'TOTAL'                 => (float)data_get($provider, 'TOTAL'),
            'TOTALCONIMPUESTO'      => (float)data_get($provider, 'TOTALCONIMPUESTO'),
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
            'STO_ART_STOCK_DISPONIBLE'      => data_get($stock, 'STO_ART_STOCK_DISPONIBLE', 0),
            'seleccionado'                  => (float) data_get($stock, 'seleccionado', 0),
        ]);
        return $st;
    }


    /** funciones para armar la cotizacion borrador de la appmovil */

    public function payloadQuotationHeaderDraft(GpUsuario $user, GpCliente $cliente, array $info): GpCotizacion
    {
        $quote = new GpCotizacion();
        $quote->fill([
            'estado'                            => 'borrador_app',
            'user_id'                           => $user->id,
            'sucursal_id'                       => data_get($info, 'sucursalId'),
            'sucursal_facturacion_id'           => data_get($info, 'sucursalId'),
            'cliente_id'                        => $cliente->id,
            'factura_posterior_despacho'        => 0,
            'factura_a_demanda'                 => 0,
            'factura_siguiente_mes'             => 0,
            'facturado'                         => 0,
            'BOD_BODEGA'                        => data_get($info, 'warehouse'),
            'PTO_VTA_ID'                        => data_get($info, 'ptoVtaCode'),
            'PTO_VTA_DESCRIPCION'               => data_get($info, 'agency'),
            'USERID'                            => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO'            => data_get($info, 'typePayment'),
            'CLI_RUC'                           => $cliente->CLI_RUC,
            'CLI_RAZON_SOCIAL'                  => $cliente->CLI_RAZON_SOCIAL,
            'CLI_CORREO_ELECT_SRI'              => $cliente->CLI_CORREO_ELECT_SRI,
            'COND_ID'                           => $cliente->CLI_CONDICION_PAGO,
            'COND_ID_ORIGINAL'                  => $cliente->CLI_CONDICION_PAGO,
            'VEND_ID'                           => $cliente->gpvendedor->VEND_ID,
            'VEND_NOMBRES'                      => $cliente->gpvendedor->VEND_NOMBRES,
            'VEND_APELLIDOS'                    => $cliente->gpvendedor->VEND_APELLIDOS,
            'despacho'                          => 'Retiro en bodega',
            'orden_de_compra'                   => null,
            'porcentage_descuento'              => $cliente->CLI_DSTO_COMERCIAL,
            'porcentage_descuentooriginal'      => $cliente->CLI_DSTO_COMERCIAL,
            'subtotal'                          => 0,
            'descuento'                         => 0,
            'subtotalmenosdescuento'            => 0,
            'iva'                               => 0,
            'total'                             => 0,
            'requiere_aprobacion_comercial'     => 'no',
            'motivo_aprobacion_comercial'       => null,
            'aprobado_comercial'                => 'no',
            'aprobador_comercial_id'            => null,
            'requiere_aprobacion_crediticia'    => 'no',
            'aprobado_crediticio'               => 'no',
            'aprobador_crediticio_id'           => null,
            'impreso'                           => 'no',
            'nota_interna'                      => null,
            'nota_cliente'                      => null,
            'nota_operaciones'                  => null,
            'impuesto'                          => TaxRateEnum::IVA_15->value,
            'origen'                            => OrderOrigenEnum::APP_MOVIL->value,
            'modificador_global'                => 0,
        ]);

        return $quote;
    }

    public function payloadQuotationDetailDraft(GpArticulo $art, int $quoteId, GpCliente $cliente)
    {
        $qd = new GpCotizacionDetalle();
        $qd->fill([
            'cotizacion_id'                 => $quoteId,
            'ART_CODIGO'                    => data_get($art, 'ART_CODIGO'),
            'ART_DESCRIPCION'               => data_get($art, 'ART_DESCRIPCION'),
            'ART_DESCRIPCION_ORIGINAL'      => data_get($art, 'ART_DESCRIPCION'),
            'CHECK_ALTERNATIVO'             => data_get($art, 'CHECK_ALTERNATIVO', 0),
            'ART_CODIGO_TRES_LETRAS'        => data_get($art, 'ART_CODIGO_TRES_LETRAS'),
            'ART_DECIMAL_CANTIDAD'          => data_get($art, 'ART_DECIMAL_CANTIDAD'),
            'ART_PLAN_UMEDIDA'              => data_get($art, 'ART_PLAN_UMEDIDA'),
            'ART_MARCA'                     => data_get($art, 'ART_MARCA'),
            'ART_MATERIAL'                  => data_get($art, 'ART_MATERIAL'),
            'ART_PESO'                      => data_get($art, 'ART_PESO'),
            'ART_TIPO_COMPRA'               => data_get($art, 'ART_TIPO_COMPRA'),
            'ART_VALOR'                     => (float)data_get($art, 'ART_VALOR'),
            'ART_VALOR_ORIGINAL'            => (float)data_get($art, 'ART_VALOR_ORIGINAL'),
            'ART_DESCUENTO'                 => 0,
            'ART_IVA'                       => (float)data_get($art, 'ART_IMP_VTA'),
            'ART_CANTIDAD'                  => (float) round(data_get($art, 'ART_CANTIDAD'), (int)data_get($art, 'ART_DECIMAL_CANTIDAD')),
            'ART_STOCK'                     => data_get($art, 'ART_STOCK', 0),
            'ART_IMP_VTA'                   => (float)data_get($art, 'ART_IMP_VTA'),
            'ART_COSTO_PROMEDIO'            => (float)data_get($art, 'ART_COSTO_PROMEDIO'),
            'descuento'                     => (float)data_get($cliente, 'CLI_DSTO_COMERCIAL'),
            'descuento_original'            => (float)data_get($cliente, 'CLI_DSTO_COMERCIAL'),
            'modificador'                   => 0,
            'valor_original_l'              => 0,
        ]);
        $qd->save();
    }



    /**
     *  proceso de creacion de registro de cotizacion historicas
     */

    public function formPayloadHistoryHeaderQuote(GpCotizacion $quote, int $version): HiCotizacion
    {
        $hi = new HiCotizacion();
        $hi->fill([
            'cot_id'                            => $quote->id,
            'co_his'                            => $version,
            'cotizacion'                        => $quote->cotizacion,
            'user_id'                           => $quote->user_id,
            'sucursal_id'                       => $quote->sucursal_id,
            'sucursal_facturacion_id'           => $quote->sucursal_facturacion_id,
            'cliente_id'                        => $quote->cliente_id,
            'prospecto_id'                      => $quote->prospecto_id,
            'estado'                            => $quote->estado,
            'facturado'                         => $quote->facturado,
            'factura_posterior_despacho'        => $quote->factura_posterior_despacho,
            'factura_a_demanda'                 => $quote->factura_a_demanda,
            'factura_siguiente_mes'             => $quote->factura_siguiente_mes,
            'BOD_BODEGA'                        => $quote->BOD_BODEGA,
            'PTO_VTA_ID'                        => $quote->PTO_VTA_ID,
            'PTO_VTA_DESCRIPCION'               => $quote->PTO_VTA_DESCRIPCION,
            'USERID'                            => $quote->USERID,
            'DESC_FORMA_PAGO_CODIGO'            => $quote->DESC_FORMA_PAGO_CODIGO,
            'CLI_RUC'                           => $quote->CLI_RUC,
            'CLI_RAZON_SOCIAL'                  => $quote->CLI_RAZON_SOCIAL,
            'CLI_CORREO_ELECT_SRI'              => $quote->CLI_CORREO_ELECT_SRI,
            'COND_ID'                           => $quote->COND_ID,
            'COND_ID_ORIGINAL'                  => $quote->COND_ID_ORIGINAL,
            'VEND_ID'                           => $quote->VEND_ID,
            'VEND_NOMBRES'                      => $quote->VEND_NOMBRES,
            'VEND_APELLIDOS'                    => $quote->VEND_APELLIDOS,
            'despacho'                          => $quote->despacho,
            'porcentage_descuentooriginal'      => $quote->porcentage_descuentooriginal,
            'porcentage_descuento'              => $quote->porcentage_descuento,
            'descuento'                         => $quote->descuento,
            'subtotalmenosdescuento'            => $quote->subtotalmenosdescuento,
            'iva'                               => $quote->iva,
            'total'                             => $quote->total,
            'requiere_aprobacion_comercial'     => $quote->requiere_aprobacion_comercial,
            'motivo_aprobacion_comercial'       => $quote->motivo_aprobacion_comercial,
            'aprobado_comercial'                => $quote->aprobado_comercial,
            'aprobador_comercial_id'            => $quote->aprobador_comercial_id,
            'aprobado_comercial_mensaje'        => $quote->aprobado_comercial_mensaje,
            'fecha_hora_aprobacion_comercial'   => $quote->fecha_hora_aprobacion_comercial,
            'requiere_aprobacion_crediticia'    => $quote->requiere_aprobacion_crediticia,
            'aprobado_crediticio'               => $quote->aprobado_crediticio,
            'aprobador_crediticio_id'           => $quote->aprobador_crediticio_id,
            'nota_interna'                      => $quote->nota_interna,
            'nota_cliente'                      => $quote->nota_cliente,
            'nota_operaciones'                  => $quote->nota_operaciones,
            'orden_de_compra'                   => $quote->orden_de_compra,
            'impreso'                           => $quote->impreso,
            'fecha_impreso'                     => $quote->fecha_impreso,
            'fecha_entrega'                     => $quote->fecha_entrega,
            'impuesto'                          => $quote->impuesto,
            'cotizacion_venta_motivo_id'        => $quote->cotizacion_venta_motivo_id,
            'recordar'                          => $quote->recordar,
            'fecha_recordatorio'                => $quote->fecha_recordatorio,
            'observacion'                       => $quote->observacion,
            'user_ing_recordatorio'             => $quote->user_ing_recordatorio,
            'origen'                            => $quote->origen,
            'fe_facturacion_reserva'            => $quote->fe_facturacion_reserva,
            'pedido_reserva_id'                 => $quote->pedido_reserva_id,
            'pedido_generado_at'                => $quote->pedido_generado_at,
            'user_rechazo_id'                   => $quote->user_rechazo_id,
            'fe_rechazo'                        => $quote->fe_rechazo,
        ]);
        return $hi;
    }


    public function formPayloadHistoryDetailQuote(int $cotId,int $detId, int $hCotId, GpCotizacionDetalle $detail): HiCotizacionDetalle
    {
        $nHDetail = new HiCotizacionDetalle();
        $nHDetail->fill([
            'hi_cot_id'                     => $hCotId,
            'cot_id'                        => $cotId,
            'det_id'                        => $detId,
            'ART_CODIGO'                    => $detail->ART_CODIGO,
            'ART_DESCRIPCION'               => $detail->ART_DESCRIPCION,
            'ART_DESCRIPCION_ORIGINAL'      => $detail->ART_DESCRIPCION_ORIGINAL,
            'CHECK_ALTERNATIVO'             => $detail->CHECK_ALTERNATIVO,
            'ART_CODIGO_TRES_LETRAS'        => $detail->ART_CODIGO_TRES_LETRAS,
            'ART_DECIMAL_CANTIDAD'          => $detail->ART_DECIMAL_CANTIDAD,
            'ART_PLAN_UMEDIDA'              => $detail->ART_PLAN_UMEDIDA,
            'ART_MARCA'                     => $detail->ART_MARCA,
            'ART_MATERIAL'                  => $detail->ART_MATERIAL,
            'ART_PESO'                      => $detail->ART_PESO,
            'ART_TIPO_COMPRA'               => $detail->ART_TIPO_COMPRA,
            'ART_VALOR'                     => $detail->ART_VALOR,
            'ART_VALOR_ORIGINAL'            => $detail->ART_VALOR_ORIGINAL,
            'ART_DESCUENTO'                 => $detail->ART_DESCUENTO,
            'ART_IVA'                       => $detail->ART_IVA,
            'ART_CANTIDAD'                  => $detail->ART_CANTIDAD,
            'ART_STOCK'                     => $detail->ART_STOCK,
            'ART_IMP_VTA'                   => $detail->ART_IMP_VTA,
            'ART_COSTO_PROMEDIO'            => $detail->ART_COSTO_PROMEDIO,
            'descuento'                     => $detail->descuento,
            'descuento_original'            => $detail->descuento_original,
            'modificador'                   => $detail->modificador,
            'valor_original_l'              => $detail->valor_original_l,
        ]);
        return $nHDetail;
    }

    public function formPayloadHistoryDetailQuoteProvider(int $hQuoteId, int $hDetailId, int $quoteId, int $detId, GpCotizacionDetalleProveedores $prov): HiCotizacionDetalleProveedores
    {
        $nHProv = new HiCotizacionDetalleProveedores();
        $nHProv->fill([
            'hi_cot_id'             => $hQuoteId,
            'hi_cot_det_id'         => $hDetailId,
            'cot_id'                => $quoteId,
            'det_id'                => $detId,
            'ID_PROVEEDOR'          => $prov->ID_PROVEEDOR,
            'DESCRIPCION_ARTICULO'  => $prov->DESCRIPCION_ARTICULO,
            'FECHA_ENTREGA'         => $prov->FECHA_ENTREGA,
            'CANTIDAD'              => $prov->CANTIDAD,
            'COSTO_UNITARIO'        => $prov->COSTO_UNITARIO,
            'IMPUESTO'              => $prov->IMPUESTO,
            'TOTAL'                 => $prov->TOTAL,
            'TOTALCONIMPUESTO'      => $prov->TOTALCONIMPUESTO,
            'NOTA'                  => $prov->NOTA,
            'MARGEN'                => $prov->MARGEN,
        ]);
        return $nHProv;
    }


    public function formPayloadHistoryDetailQuoteStock(int $hQuoteId, int $hDetailId, int $quoteId, int $detId, GpCotizacionDetalleStock $stock): HiCotizacionDetalleStock
    {
        $nHStock = new HiCotizacionDetalleStock();
        $nHStock->fill([
            'hi_cot_id'                     => $hQuoteId,
            'hi_cot_det_id'                 => $hDetailId,
            'cot_id'                        => $quoteId,
            'det_id'                        => $detId,
            'STO_ART_BODEGA'                => $stock->STO_ART_BODEGA,
            'STO_ART_STOCK_DISPONIBLE'      => $stock->STO_ART_STOCK_DISPONIBLE,
            'seleccionado'                  => $stock->seleccionado,
        ]);


        return $nHStock;
    }
}
