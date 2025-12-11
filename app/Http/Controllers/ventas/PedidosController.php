<?php

namespace App\Http\Controllers\ventas;

use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpArticulo;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\GpClienteSucursal;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Http\Controllers\Controller;
use App\Models\GpPedidoDetalleStock;
use Illuminate\Support\Facades\Auth;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\traslados\GpTrasladosVentas;
use App\Http\Controllers\FuncionesController;
use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Http\Controllers\WebserviceController;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpUsuario;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PedidosController extends Controller
{
    use JsonResponseTrait;
    protected $apiGp;
    protected $orderService;
    protected $approvalService;
    protected $invoiceService;
    protected $functionService;
    public function __construct(
        ApiGpService $apiGp,
        OrderServiceInterface $orderService,
        ApprovalServiceInterface $approvalService,
        InvoiceServiceInterface $invoiceService,
        FunctionsServiceInterface $functionService
    ) {
        $this->orderService = $orderService;
        $this->apiGp = $apiGp;
        $this->approvalService = $approvalService;
        $this->invoiceService = $invoiceService;
        $this->functionService = $functionService;
    }


    //en proceso de mejora
    public function guardarpedido(Request $request)
    {
        $orderData = $request->all();
        $user = Auth::user();
        $maxRetries = 3;
        $attempt = 0;
        while (true) {
            try {
                $pedido = DB::transaction(function () use ($orderData, $user) {
                    return $this->orderService->storeOrder($orderData, $user);
                }, 5);
                return $this->buildSuccessResponse("Pedido {$pedido->pedido} guardado", 200);
            } catch (QueryException $e) {
                $mysqlCode = $e->errorInfo[1] ?? null;
                // ¿es un error de concurrencia y aún puedo reintentar?
                if (in_array($mysqlCode, [1213, 1205]) && $attempt < $maxRetries) {
                    $attempt++;

                    Log::warning('Deadlock/lock wait guardarpedido, reintentando...', [
                        'attempt' => $attempt,
                        'mysqlCode' => $mysqlCode,
                        'msg' => $e->getMessage(),
                    ]);

                    // pasa al siguiente ciclo del while
                    continue;
                }

                // si no es 1213/1205 o ya superó los reintentos, lo tratamos como error real
                Log::error('Error in storeOrder (guardar pedido)', [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return $this->buildErrorResponse('No se pudo guardar el pedido, intente de nuevo.', 500);

            } catch (DomainException $e) {
                // 👈 este es el que puede lanzar tu flujo de stock
                return $this->buildErrorResponse($e->getMessage(), 422);
            } catch (\Throwable $e) {
                // cualquier otra excepción que no sea de SQL
                Log::error('Error in storeOrder (guardar pedido)', [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return $this->buildErrorResponse($e->getMessage(), 500);
            }
        }
    }

    public function facturarpedido(Request $request)
    {
        $data = $request->validate([
            'id' => 'required',
        ]);
        try {
            $order = DB::transaction(function () use ($data) {
                return $this->orderService->generateInvoiceOrder($data['id']);
            });
            Log::info("Pedido {$order->pedido} facturado desde Facturar Pedido - generateInvoiceOrder - facturarpedido");
            return $this->buildSuccessResponse("Pedido {$order->pedido} facturado", 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function facturarpedidoTest($codPedido)
    {
        $pedido = GpPedido::where('pedido', $codPedido)->first();

        return $this->orderService->generateInvoiceOrder($pedido->id);
    }



    public function generarordendespacho($id)
    {
        $pedido = GpPedido::where('id', $id)
            ->first();
        $this->invoiceService->generateDisptachOrder($pedido);
    }





    public function corregirenviando(): void
    {

        $webservices = new WebserviceController();
        $enviando = GpPedido::where('estado', 'enviando')
            ->where('updated_at', '<=', Carbon::now()->subMinutes(5)->toDateTimeString())->get();

        foreach ($enviando as $pedido) {
            $this->invoiceService->respuestaFacturaVenta($pedido);
        }
    }

    public function sendfacturasgp()
    {

        $webservices = new WebserviceController();

        $pedidos = GpPedido::where('estado', 'espera')
            //->where('factura_siguiente_mes', 0)
            //where('id',69)
            ->get();

        foreach ($pedidos as $pedido) {

            // $this->checkenviarfactura($pedido);
            $this->invoiceService->checkSendInvoice($pedido);
        }
        $traslados = GpPedido::where('estado', 'orden_de_compra')->get();
        foreach ($traslados as $pedido) {
            $listo = 'yes';
            $detalleProveedores = GpPedidoDetalleProveedores::join('gp_orden_de_compra_generados', 'gp_orden_de_compra_generados.detalle_id', 'gp_pedido_detalle_proveedores.detalle_id')
                ->where('pedido_id', $pedido->id)
                ->select('gp_pedido_detalle_proveedores.*', 'gp_orden_de_compra_generados.ORDEN_COMPRA_RECIBO')
                ->get();

            if (!count($detalleProveedores)) {
                $listo = 'no';
            }

            foreach ($detalleProveedores as $prov) {
                if ($prov->recibido == 'no') {
                    $listo = 'no';
                }

                $parameters = array(
                    'documento_origen' => $prov->ORDEN_COMPRA_RECIBO,
                    'tipo_documento' => 1,
                );

                $result = $webservices->transaccional('MST_Estado_Recibo_Compra', $parameters);

                if (isset($result['MST_Estado_Recibo_CompraResult']['CS_ESTADO_RECIBO_COMPRA'][0]['REC_COM_ESTADO_RECEPCION']) && $result['MST_Estado_Recibo_CompraResult']['CS_ESTADO_RECIBO_COMPRA'][0]['REC_COM_ESTADO_RECEPCION'] != 'PROCESADO') {
                    $listo = 'no';
                }
            }
            if ($listo == 'yes') {
                $pedido->estado = 'espera';
                $pedido->save();
                $this->invoiceService->generateDisptachOrder($pedido);
                $this->invoiceService->sendInvoiceGp($pedido);
            }
        }
    }


    public function verpedidos(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {



        $data = $request->validate([
            'puntodeventa_id' => 'required',
            'estado' => 'required',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'cliente' => '',
        ]);

        $pedidos = GpPedido::where('gp_pedidos.PTO_VTA_ID', $data['puntodeventa_id'])
            ->leftJoin('gp_cotizacions', 'gp_cotizacions.id', 'gp_pedidos.cotizacion_id')
            ->select('gp_pedidos.*', 'gp_cotizacions.cotizacion');

        if ($data['estado'] != 'todos') {
            $pedidos = $pedidos->where('gp_pedidos.estado', $data['estado']);
        }
        if (isset($data['fecha_desde']) && $data['fecha_desde'] != '' && isset($data['fecha_hasta']) && $data['fecha_hasta'] != '') {
            $pedidos = $pedidos->whereDate('gp_pedidos.created_at', '>=', $data['fecha_desde'] . ' 00:00:00');
            $pedidos = $pedidos->whereDate('gp_pedidos.created_at', '<=', $data['fecha_hasta'] . ' 23:59:59');
        }

        if (isset($data['cliente'])) {
            $pedidos = $pedidos->where('gp_pedidos.CLI_RUC', $data['cliente']['CLI_RUC']);
        }

        $pedidos = $pedidos->orderBy('gp_pedidos.created_at', 'desc')->limit(500)->get();

        foreach ($pedidos as $pedido) {

            $pedido->direccion_entrega = GpClienteSucursal::where('id', $pedido->sucursal_id)->first();

            if ($pedido->requiere_aprobacion_comercial == 'yes' && $pedido->aprobado_comercial == 'no') {
                $pedido->imprimir = 'no';
            } elseif ($pedido->requiere_aprobacion_crediticia == 'yes' && $pedido->aprobado_crediticio == 'no') {
                $pedido->imprimir = 'no';
            } elseif ($pedido->estado == 'rechazado') {
                $pedido->imprimir = 'no';
            } else {
                $pedido->imprimir = 'yes';
            }
        }

        return response([
            'success' => true,
            'pedidos' => $pedidos,
        ]);
    }

    public function detallepedido(Request $request)
    {
        try {
            $data = $request->all();
            $result = $this->orderService->detailOrderById($data);
            return response()->json([
                'success' => true,
                ...$result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle del pedido: ' . $e->getMessage()
            ], 500);
        }
    }


    public function consultarexistente(Request $request)
    {
        try {
            $data = $request->all();
            $pedido = $this->orderService->checkExistence($data);
            return $this->successResponse('Existencia Obtenida Correctamente', $pedido, 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            Log::error('Error en checkExistence', ['exception' => $e]);

            return $this->buildErrorResponse($e->getMessage(), 500);
        }

    }

    public function facturarexistente(Request $request)
    {
        try {
            $facturarExistente = $request->all();
            $data = $facturarExistente['pedido'];
            $detalle = $facturarExistente['detalle'];
            $user = Auth::user();
            $pedido = DB::transaction(function () use ($data, $detalle, $user) {
                return $this->orderService->generateInvoiceFromExistingOrder($data, $detalle, $user);
            });
            return $this->buildSuccessResponse('Pedido Generado Correctamente' . ' ' . $pedido->pedido . ' ' . ' en Facturar Existente', 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            Log::error('Error in facturarexistente', [
                'error' => $e->getMessage(),
                'data' => $data['id'],
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    public function consultardespachado(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $facturarExistente = array();

        $data['subtotal'] = 0;
        $data['descuento'] = 0;
        $data['iva'] = 0;
        $data['subtotalmenosdescuento'] = 0;
        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();
        $funciones = new FuncionesController();

        foreach ($detalle as $key => $articulo) {

            if ($articulo->trasladado == 0) {
                unset($detalle[$key]);
                continue;
            }

            $proveedores = $funciones->getitemproveedores($data, $articulo, false);
            $array_prov = array();

            if (count($proveedores['proveedores']) > 0) {
                foreach ($proveedores['proveedores'] as $proveedor) {
                    $array_prov[] = $proveedor;
                }
            }

            $articulo->proveedores = $array_prov;

            $articulo->ART_CANTIDAD = $articulo->trasladado;

            $data['subtotal'] += $articulo->ART_CANTIDAD * $articulo->ART_VALOR;
            $data['descuento'] += (float) number_format($articulo->ART_DESCUENTO * $articulo->ART_CANTIDAD, 2, '.', '');
            $data['subtotalmenosdescuento'] += number_format(($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD, 2, '.', '');
            $data['iva'] += (float) number_format((($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD) * ($articulo->ART_IMP_VTA / 100), 2, '.', '');
        }

        $data['descuento'] = number_format($data['descuento'], 2, '.', '');
        $data['subtotalmenosdescuento'] = number_format($data['subtotalmenosdescuento'], 2, '.', '');
        $data['iva'] = number_format($data['iva'], 2, '.', '');
        $data['total'] = number_format($data['subtotalmenosdescuento'] + (float) $data['iva'], 2, '.', '');

        $facturarExistente['pedido'] = $data;
        $facturarExistente['detalle'] = $detalle;

        return response([
            'success' => true,
            'facturar_despachado' => $facturarExistente,
        ]);
    }


    public function facturardespachado(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $facturarDespachado = $request->all();

        $data = $facturarDespachado['pedido'];

        $funciones = new FuncionesController();
        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();

        $eliminar = array('');

        foreach ($detalle as $key => $articulo) {
            $stocks = GpPedidoDetalleStock::where('detalle_id', $articulo->id)
                ->where('STO_ART_BODEGA', $data['BOD_BODEGA'])
                ->first();

            if ($articulo->ART_TIPO_COMPRA != 'SERV') {
                if ($articulo->trasladado == $articulo->ART_CANTIDAD) {
                    $articulo->delete();
                } elseif ($articulo->trasladado > 0 && $articulo->trasladado < $articulo->ART_CANTIDAD) {
                    $articulo->ART_CANTIDAD = $articulo->ART_CANTIDAD - $articulo->trasladado;
                    $articulo->trasladado = 0;
                    $articulo->save();

                    $stocks->seleccionado = $articulo->ART_CANTIDAD;
                    $stocks->reservado = $articulo->ART_CANTIDAD;
                    $stocks->save();
                }

                $proveedores = $funciones->getitemproveedores($data, $articulo, false);

                foreach ($proveedores['proveedores'] as $proveedor) {
                    if (($proveedor->ingresado - $proveedor->ejecutado) > 0) {
                        //echo 'Eliminar Proveedor: '.$proveedor->id.'<br>';
                        $articulo->ART_CANTIDAD -= $proveedor->ingresado - $proveedor->ejecutado;
                        if ($proveedor->ingresado == $proveedor->CANTIDAD) {
                            unset($proveedor->orden_detalle);

                            $eliminar['proveedores'][] = $proveedor;

                            //$proveedor->delete();
                        } else {
                            unset($proveedor->orden_detalle);
                            $proveedor->save();
                        }
                    }
                }

                if (count($proveedores['proveedores'])) {
                    if ($articulo->ART_CANTIDAD == 0) {
                        //echo 'Por eliminar L: '.$articulo->ART_CODIGO.'<br>';
                        $eliminar['articulos'][] = $articulo;
                        //$articulo->delete();
                    } else {
                        //echo 'Editar la cantidad del L '.$articulo->ART_CODIGO.' a: '.$articulo->ART_CANTIDAD.'<br>';
                        $articulo->save();
                    }
                }
            } else {
                $articulo->delete();
            }
        }

        $articulos = $facturarDespachado['detalle'];

        $user = GpUsuario::where('id', Auth::user()->id)->first();
        $pedido = new GpPedido;

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

        if (!$ultimoPedido) {
            $ultimoPedido = new \stdClass();
            $ultimoPedido->id = env('SECUENCIAL_PEDIDO');
            $pedido->id = $ultimoPedido->id + 1;
        }
        $pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);
        $pedido->user_id = $user->id;
        $pedido->sucursal_id = $data['sucursal_id'];
        $pedido->sucursal_facturacion_id = $data['sucursal_facturacion_id'];
        $pedido->cliente_id = $data['cliente_id'];
        $pedido->factura_posterior_despacho = $data['factura_posterior_despacho'];
        $pedido->factura_a_demanda = $data['factura_a_demanda'];
        $pedido->factura_siguiente_mes = $data['factura_siguiente_mes'];
        $pedido->cotizacion_id = $data['cotizacion_id'];
        $pedido->BOD_BODEGA = $data['BOD_BODEGA'];
        $pedido->PTO_VTA_ID = $data['PTO_VTA_ID'];
        $pedido->PTO_VTA_DESCRIPCION = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $pedido->USERID = $user->USERID;
        $pedido->DESC_FORMA_PAGO_CODIGO = $data['DESC_FORMA_PAGO_CODIGO'];
        $pedido->CLI_RUC = $data['CLI_RUC'];
        $pedido->CLI_RAZON_SOCIAL = $data['CLI_RAZON_SOCIAL'];
        $pedido->CLI_CORREO_ELECT_SRI = $data['CLI_CORREO_ELECT_SRI'];
        $pedido->COND_ID = $data['COND_ID'];
        $pedido->COND_ID_ORIGINAL = $data['COND_ID_ORIGINAL'];
        $pedido->VEND_ID = $data['VEND_ID'];
        $pedido->VEND_NOMBRES = $data['VEND_NOMBRES'];
        $pedido->VEND_APELLIDOS = $data['VEND_APELLIDOS'];
        $pedido->despacho = $data['despacho'];
        $pedido->porcentage_descuentooriginal = $data['porcentage_descuentooriginal'];
        $pedido->porcentage_descuento = $data['porcentage_descuento'];
        $pedido->subtotal = number_format((float) $data['subtotal'], 2, '.', '');
        $pedido->descuento = number_format((float) $data['descuento'], 2, '.', '');
        $pedido->subtotalmenosdescuento = number_format((float) $data['subtotalmenosdescuento'], 2, '.', '');
        $pedido->iva = number_format((float) $data['iva'], 2, '.', '');
        $pedido->total = number_format((float) $data['total'], 2, '.', '');
        $pedido->requiere_aprobacion_comercial = $data['requiere_aprobacion_comercial'];
        $pedido->aprobado_comercial = $data['aprobado_comercial'];
        $pedido->aprobador_comercial_id = $data['aprobador_comercial_id'];
        $pedido->requiere_aprobacion_crediticia = $data['requiere_aprobacion_crediticia'];
        $pedido->aprobado_crediticio = $data['aprobado_crediticio'];
        $pedido->aprobador_crediticio_id = $data['aprobador_crediticio_id'];
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->nota_cliente = $data['nota_cliente'];
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->pedido_origen = $data['pedido'];
        $pedido->despachado = 1;
        $pedido->impuesto = env('IVA_IMPUESTO');

        if ($pedido->save()) {
            $i = 0;
            foreach ($articulos as $det) {
                $i++;
                $detalle = $this->savePedidoDetalle($i, $pedido, $det);

                if ($det['ART_TIPO_COMPRA'] != 'SERV') {
                    if (count($det['proveedores']) > 0) {
                        foreach ($det['proveedores'] as $pro) {
                            $pro['recibido'] = 'yes';
                            $proveedor = $this->savePedidoDetalleProveedores($pedido, $detalle, $pro);
                            $proveedor->save();

                            foreach ($pro['orden_detalle'] as $orden) {
                                $ordenCompra = GpOrdenDeCompra::where('DOC_GENERADO', $orden['ORDEN_COMPRA_DOCUMENTO'])
                                    ->where('detalle_id', $det['id'])
                                    ->where('detalle_proveedores_id', $pro['id'])
                                    ->first();

                                $ejecutado = GpPedidoDetalleProveedores::where('id', $pro['id'])->first();

                                if ($ejecutado) {
                                    $ejecutado->ejecutado += $det['ART_CANTIDAD'];
                                    $ejecutado->save();
                                }

                                if (!$ejecutado) {
                                    $ordenCompra->ORDEN_COMPRA_DOCU_ORIGEN = $pedido->pedido;
                                    $ordenCompra->detalle_id = $detalle->id;
                                    $ordenCompra->detalle_proveedores_id = $proveedor->id;
                                    $ordenCompra->save();
                                } else {
                                    $nueva_orden = $ordenCompra->replicate();
                                    $nueva_orden->ORDEN_COMPRA_DOCU_ORIGEN = $pedido->pedido;
                                    $nueva_orden->detalle_id = $detalle->id;
                                    $nueva_orden->detalle_proveedores_id = $proveedor->id;
                                    $nueva_orden->ORDEN_COMPRA_CANTIDAD = $det['ART_CANTIDAD'];
                                    $nueva_orden->save();
                                }

                                $ordenCompraGenerado = GpOrdenDeCompraGenerado::where('orden_id', $ordenCompra->id)
                                    ->where('detalle_id', $det['id'])
                                    ->where('detalle_proveedores_id', $pro['id'])
                                    ->first();

                                if (!$ejecutado) {
                                    $ordenCompraGenerado->detalle_id = $detalle->id;
                                    $ordenCompraGenerado->detalle_proveedores_id = $proveedor->id;
                                    $ordenCompraGenerado->save();
                                } else {
                                    $nueva_ordenCompraGenerado = $ordenCompraGenerado->replicate();
                                    $nueva_ordenCompraGenerado->orden_id = $nueva_orden->id;
                                    $nueva_ordenCompraGenerado->detalle_id = $detalle->id;
                                    $nueva_ordenCompraGenerado->detalle_proveedores_id = $proveedor->id;
                                    $nueva_ordenCompraGenerado->estado = 'recibido';
                                    $nueva_ordenCompraGenerado->save();

                                    $ordenCompraGeneradoDetalle = GpOrdenDeCompraGeneradoDetalle::where('orden_generada_id', $ordenCompraGenerado->id)->get();

                                    foreach ($ordenCompraGeneradoDetalle as $ocgd) {
                                        $nueva_ocgd = $ocgd->replicate();
                                        $nueva_ocgd->orden_generada_id = $nueva_ordenCompraGenerado->id;
                                        $nueva_ocgd->detalle_proveedores_id = $proveedor->id;
                                        $nueva_ocgd->save();
                                    }
                                }
                            }
                        }
                    }
                    $stock = new GpPedidoDetalleStock();
                    $stock->pedido_id = $pedido->id;
                    $stock->detalle_id = $detalle->id;
                    $stock->STO_ART_BODEGA = $pedido->BOD_BODEGA;
                    $stock->STO_ART_STOCK_DISPONIBLE = $det['ART_CANTIDAD'];
                    $stock->seleccionado = $det['ART_CANTIDAD'];
                    $stock->reservado = $det['ART_CANTIDAD'];
                    $stock->save();
                }
            }
            $deleteProviders = data_get($eliminar, 'proveedores') ?? [];
            $deleteArticles = data_get($eliminar, 'articulos') ?? [];
            foreach ($deleteProviders as $proveedor) {
                $proveedor->delete();
            }

            foreach ($deleteArticles as $articulo) {
                $articulo->delete();
            }

            // $this->recalcularpedido($data['id']);
            // $this->recalcularpedido($pedido->id);
            $this->functionService->recalculateOrder($data['id']);
            $this->functionService->recalculateOrder($pedido->id);

            $pedido_anterior = GpPedido::where('id', $data['id'])->first();
            $pedido = GpPedido::where('id', $pedido->id)->first();

            GpGuiaRemision::where('pedido', $pedido_anterior->pedido)->update(['pedido' => $pedido->pedido]);

            // $this->checkenviarfactura($pedido, true, false);
            $this->invoiceService->checkSendInvoice($pedido, true, false);
        }
        return response([
            'success' => true,
            'message' => 'El pedido ha sido dividido'
        ]);
    }
    public function facturardespachadoBK(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $facturarDespachado = $request->all();
        $data = $facturarDespachado['pedido'];

        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();

        foreach ($detalle as $key => $articulo) {
            $stocks = GpPedidoDetalleStock::where('detalle_id', $articulo->id)
                ->where('STO_ART_BODEGA', $data['BOD_BODEGA'])
                ->first();

            if ($articulo->ART_TIPO_COMPRA != 'SERV') {
                if (!$articulo->trasladado) {
                    $articulo->delete();
                } elseif ($articulo->trasladado > 0) {
                    $articulo->ART_CANTIDAD = $articulo->trasladado;
                    $articulo->save();

                    $stocks->seleccionado = $articulo->trasladado;
                    $stocks->save();
                }
            }
        }

        $this->functionService->recalculateOrder($data['id']);

        $pedido = GpPedido::where('id', $data['id'])->first();

        GpOrdenDespacho::where('pedido_id', $pedido->id)->update(['estado' => 'despachada']);

        // $this->checkenviarfactura($pedido, true, false);
        $this->invoiceService->checkSendInvoice($pedido, true, false);


        return response([
            'success' => true,
            'message' => 'El pedido ha sido modificado y facturado'
        ]);
    }

    public function recalcularpedido($id): void
    {
        $this->functionService->recalculateOrder($id);
        // $funciones = new FuncionesController();
        // $pedido = GpPedido::where('id', $id)->first();
        // $funciones->recalcularpedido($pedido);
    }

    public function corregirpedidos()
    {
        $pedidos = GpPedido::whereNotNull('pedido_origen')->get();
        foreach ($pedidos as $pedido) {
            // $this->recalcularpedido($pedido->id);
            $this->functionService->recalculateOrder($pedido->id);
        }
    }

    public function recuperarpedido(Request $request)
    {
        $data = $request->all();
        $user = Auth::user();
        return $this->orderService->restoreOrder($data, $user);
    }

    public function copiarpedido(Request $request)
    {
        $data = $request->all();
        $user = Auth::user();
        return $this->orderService->copyOrder($data, $user);
    }




    public function imprimirpedido($id): \Illuminate\Http\Response|string //\Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|string|\Illuminate\Contracts\Foundation\Application
    {
        try {
            $pedido = $this->orderService->getOrderInfo($id);
            if (is_string($pedido)) {
                abort(403, $pedido); // Ej: 'No permitido'
            }
            $pdf = PDF::loadView('pedidoPDF', $pedido);
            return $pdf->stream('pedido-' . $pedido['pedido'] . '.pdf');
        } catch (\Exception $e) {
            report($e);
            abort(500, 'Error al generar el PDF del pedido');
        }
    }
    public function imprimirpedidoimagenes($id): \Illuminate\Http\Response|string //\Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|string|\Illuminate\Contracts\Foundation\Application
    {
        try {
            $pedido = $this->orderService->getOrderInfo($id);
            if (is_string($pedido)) {
                abort(403, $pedido); // Ej: 'No permitido'
            }
            $pdf = PDF::loadView('pedidoimagenesPDF', $pedido);
            return $pdf->stream('pedido-' . $pedido['pedido'] . '.pdf');
        } catch (\Exception $e) {
            report($e);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function imprimirpedidoride($id)
    {
        try {

            $pedido = $this->orderService->generateInvoiceRide($id);

            if (is_string($pedido)) {
                abort(403, $pedido); // Ej: 'No permitido'
            }
            $pdf = PDF::loadView('pedidoridePDF', $pedido);
            return $pdf->stream('ride-' . $pedido['pedido'] . '.pdf');
        } catch (\Throwable $e) {
            // report($e);
            return $this->buildErrorResponse($e->getMessage(), 404);
        }
    }

    public function imprimirpedidoridesinimagen($id)
    {
        try {
            $pedido = $this->orderService->generateInvoiceRide($id);

            if (is_string($pedido)) {
                abort(403, $pedido); // Ej: 'No permitido'
            }
            $pdf = PDF::loadView('pedidoridesinImagenPDF', $pedido);
            return $pdf->stream('ride-' . $pedido['pedido'] . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Error en imprimirpedidoridesinimagen', [
                $e->getMessage()
            ]);
            return $this->buildErrorResponse($e->getMessage(), 404);
        }
    }

    public function imprimirpedidoxml($id)
    {
        try {
            $xmlContent = $this->orderService->generateInvoiceXml($id);

            if (empty($xmlContent['xml']) || empty($xmlContent['factura'])) {
                return $this->buildErrorResponse('No se pudo generar el XML.', 422);
            }

            $nombreArchivo = 'factura_' . $xmlContent['factura'] . '.xml';

            return response($xmlContent['xml'], 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"');
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }
    public function despachoenproceso($id)
    {
        try {
            $order = $this->orderService->verifyOrderDispatchOrItemLRecived($id);
            return $this->successResponse('Información Obtenida Correctamente', $order, 200);
        } catch (\Throwable $th) {
            Log::error('Error al obtener el despacho en proceso: ', [
                'error' => $th->getMessage(),
                'pedido_id' => $id,
                'linea' => $th->getLine()
            ]);
            return $this->buildErrorResponse('Opps! Error al obtener el despacho en proceso', 500);
        }
    }



    public function guardarordendecompra(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $pedido = GpPedido::where('id', $data['id'])->get()->first();
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->save();

        return response([
            'success' => true,
            'message' => 'Orden de compra guardada'
        ]);
    }

    public function guardarmensaje(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $pedido = GpPedido::where('id', $data['id'])->get()->first();
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->aprobado_comercial_mensaje = $data['aprobado_comercial_mensaje'];
        $pedido->aprobado_crediticio_mensaje = $data['aprobado_crediticio_mensaje'];
        $pedido->save();

        return response([
            'success' => true,
            'message' => 'Todo guardado'
        ]);
    }

    public function facturarcotizacion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = GpUsuario::where('id', Auth::user()->id)->first();

        $detalle = GpCotizacionDetalle::where('cotizacion_id', $data['id'])->get()->toArray();
        foreach ($detalle as $key => $det) {
            $proveedores = GpCotizacionDetalleProveedores::where('detalle_id', $det['id'])->get()->toArray();
            $detalle[$key]['proveedores'] = $proveedores;

            $stock = GpCotizacionDetalleStock::where('detalle_id', $det['id'])->get()->toArray();
            $detalle[$key]['stock'] = $stock;
        }

        $data['cotizacion'] = $detalle;


        $requiere_aprobacion_comercial = 'no';

        $requiere_aprobacion_crediticia = $this->approvalService->checkoutApprovalCredit($data);

        $pedido = new GpPedido;

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

        if (!$ultimoPedido) {
            $ultimoPedido = new \stdClass();
            $ultimoPedido->id = 0;
        }
        $pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);
        $pedido->user_id = $user->id;
        $pedido->sucursal_id = $data['sucursal_id'];
        $pedido->sucursal_facturacion_id = $data['sucursal_facturacion_id'];
        $pedido->cliente_id = $data['cliente_id'];
        $pedido->factura_posterior_despacho = $data['factura_posterior_despacho'];
        $pedido->factura_a_demanda = $data['factura_a_demanda'];
        $pedido->factura_siguiente_mes = $data['factura_siguiente_mes'];
        $pedido->cotizacion_id = $data['id'];
        $pedido->BOD_BODEGA = $data['BOD_BODEGA'];
        $pedido->PTO_VTA_ID = $data['PTO_VTA_ID'];
        $pedido->PTO_VTA_DESCRIPCION = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $pedido->USERID = $user->USERID;
        $pedido->DESC_FORMA_PAGO_CODIGO = $data['DESC_FORMA_PAGO_CODIGO'];
        $pedido->CLI_RUC = $data['CLI_RUC'];
        $pedido->CLI_RAZON_SOCIAL = $data['CLI_RAZON_SOCIAL'];
        $pedido->CLI_CORREO_ELECT_SRI = $data['CLI_CORREO_ELECT_SRI'];
        $pedido->COND_ID = $data['COND_ID'];
        $pedido->COND_ID_ORIGINAL = $data['COND_ID_ORIGINAL'];
        $pedido->VEND_ID = $data['VEND_ID'];
        $pedido->VEND_NOMBRES = $data['VEND_NOMBRES'];
        $pedido->VEND_APELLIDOS = $data['VEND_APELLIDOS'];
        $pedido->despacho = $data['despacho'];
        $pedido->porcentage_descuentooriginal = $data['porcentage_descuentooriginal'];
        $pedido->porcentage_descuento = $data['porcentage_descuento'];
        $pedido->subtotal = (float) $data['subtotal'];
        $pedido->descuento = (float) $data['descuento'];
        $pedido->subtotalmenosdescuento = (float) $data['subtotal'] - (float) $data['descuento'];
        $pedido->iva = (float) $data['iva'];
        $pedido->total = (float) $data['total'];
        $pedido->requiere_aprobacion_comercial = $requiere_aprobacion_comercial;
        $pedido->aprobado_comercial = 'no';
        $pedido->aprobador_comercial_id = null;
        $pedido->requiere_aprobacion_crediticia = $requiere_aprobacion_crediticia;
        $pedido->aprobado_crediticio = 'no';
        $pedido->aprobador_crediticio_id = null;
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->nota_cliente = $data['nota_cliente'];
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->impuesto = env('IVA_IMPUESTO');

        if ($pedido->save()) {
            $i = 0;
            foreach ($data['cotizacion'] as $det) {
                $i++;
                $detalle = $this->savePedidoDetalle($i, $pedido, $det);

                if (count($det['proveedores']) > 0) {
                    foreach ($det['proveedores'] as $pro) {
                        $proveedor = $this->savePedidoDetalleProveedores($pedido, $detalle, $pro);
                        $proveedor->save();
                    }
                }
                $this->savePedidoDetalleStock($det['stock'], $pedido, $detalle);
            }

            GpCotizacion::where('id', $data['id'])->update(['estado' => 'procesado']);

            // $this->checkenviarfactura($pedido);
            $this->invoiceService->checkSendInvoice($pedido);
        }
        return response([
            'success' => true,
            'message' => 'El pedido ha sido guardado'
        ]);
    }

    /**
     * @param array $data
     * @param GpPedido $pedido
     * @param $PTO_VTA_DESCRIPCION
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|null $user
     * @param array $requiere_aprobacion_comercial
     * @param string $requiere_aprobacion_crediticia
     * @return void
     */
    public function setguardarpedido(array $data, GpPedido $pedido, $PTO_VTA_DESCRIPCION, \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|null $user, array $requiere_aprobacion_comercial, string $requiere_aprobacion_crediticia): void
    {
        $user = GpUsuario::where('id', Auth::user()->id)->first();
        $pedido->PTO_VTA_ID = $data['PTO_VTA_ID'];
        $pedido->PTO_VTA_DESCRIPCION = $PTO_VTA_DESCRIPCION;
        $pedido->USERID = $user->USERID;
        $pedido->DESC_FORMA_PAGO_CODIGO = $data['DESC_FORMA_PAGO_CODIGO'];
        $pedido->CLI_RUC = $data['CLI_RUC'];
        $pedido->CLI_RAZON_SOCIAL = $data['CLI_RAZON_SOCIAL'];
        $pedido->CLI_CORREO_ELECT_SRI = $data['CLI_CORREO_ELECT_SRI'];
        $pedido->COND_ID = $data['COND_ID'];
        $pedido->COND_ID_ORIGINAL = $data['COND_ID_ORIGINAL'];
        $pedido->VEND_ID = $data['VEND_ID'];
        $pedido->VEND_NOMBRES = $data['VEND_NOMBRES'];
        $pedido->VEND_APELLIDOS = $data['VEND_APELLIDOS'];
        $pedido->despacho = $data['despacho'];
        $pedido->porcentage_descuentooriginal = $data['porcentage_descuentooriginal'];
        $pedido->porcentage_descuento = $data['porcentage_descuento'];
        $pedido->subtotal = number_format((float) $data['subtotal'] + (float) $data['descuento'], 2, '.', '');
        $pedido->descuento = number_format((float) $data['descuento'], 2, '.', '');
        $pedido->subtotalmenosdescuento = number_format((float) $data['subtotal'], 2, '.', '');
        $pedido->iva = number_format((float) $data['iva'], 2, '.', '');
        $pedido->total = number_format((float) $data['total'], 2, '.', '');
        $pedido->requiere_aprobacion_comercial = $requiere_aprobacion_comercial['requiere'];
        $pedido->motivo_aprobacion_comercial = $requiere_aprobacion_comercial['motivo'];
        $pedido->aprobado_comercial = 'no';
        $pedido->aprobador_comercial_id = null;
        $pedido->requiere_aprobacion_crediticia = $requiere_aprobacion_crediticia;
        $pedido->aprobado_crediticio = 'no';
        $pedido->aprobador_crediticio_id = null;
        $pedido->motivo_crediticio = $data['motivo_crediticio'];
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->nota_cliente = $data['nota_cliente'];
        $pedido->orden_de_compra = $data['orden_de_compra'];
    }

    /**
     * @param int $i
     * @param GpPedido $pedido
     * @param mixed $det
     * @return GpPedidoDetalle
     */
    public function savePedidoDetalle(int $i, GpPedido $pedido, mixed $det): GpPedidoDetalle
    {

        //        $articulos_array_l = array();
        //        $articulo = (object)$det;
        //        $funciones = new FuncionesController();
        //        $articulo->disponible = $funciones->checkarticulodisponible($articulo);
        //        if(!$articulo->disponible){
        //            $clase = GpArticulo::where('ART_CODIGO', $articulo->ART_CODIGO)->first();
        //            $articulo->ART_CODIGO = $funciones->buscareledisponible($articulos_array_l, $clase->ART_CLASE_ARTICULO);
        //            $articulos_array_l[] = $articulo->ART_CODIGO;
        //        }
        //        $det['ART_CODIGO'] = $articulo->ART_CODIGO;

        $detalle = new GpPedidoDetalle;
        $detalle->pedido_id = $pedido->id;
        $detalle->orden = $i;
        $detalle->ART_CODIGO = $det['ART_CODIGO'];
        $detalle->ART_DESCRIPCION = $det['ART_DESCRIPCION'];
        $detalle->ART_DESCRIPCION_ORIGINAL = $det['ART_DESCRIPCION_ORIGINAL'];
        $detalle->CHECK_ALTERNATIVO = $det['CHECK_ALTERNATIVO'] ?? 0;
        $detalle->ART_CODIGO_TRES_LETRAS = $det['ART_CODIGO_TRES_LETRAS'];
        $detalle->ART_DECIMAL_CANTIDAD = (int) $det['ART_DECIMAL_CANTIDAD'];
        $detalle->ART_PLAN_UMEDIDA = $det['ART_PLAN_UMEDIDA'];
        $detalle->ART_MARCA = $det['ART_MARCA'];
        $detalle->ART_MATERIAL = $det['ART_MATERIAL'];
        $detalle->ART_PESO = $det['ART_PESO'];
        $detalle->ART_TIPO_COMPRA = $det['ART_TIPO_COMPRA'];
        $detalle->ART_VALOR = (float) $det['ART_VALOR'];
        $detalle->ART_VALOR_ORIGINAL = (float) $det['ART_VALOR_ORIGINAL'];
        $detalle->ART_DESCUENTO = (float) $det['ART_DESCUENTO'];
        $detalle->ART_IVA = (float) $det['ART_IVA'];
        $detalle->ART_CANTIDAD = (float) $det['ART_CANTIDAD'];
        $detalle->ART_STOCK = (float) $det['ART_STOCK'];
        $detalle->ART_IMP_VTA = (float) $det['ART_IMP_VTA'];
        $detalle->ART_COSTO_PROMEDIO = (float) $det['ART_COSTO_PROMEDIO'];
        $detalle->descuento = (float) $det['descuento'];
        $detalle->descuento_original = (float) $det['descuento_original'];
        $detalle->modificador = (float) $det['modificador'];
        $detalle->save();
        return $detalle;
    }

    /**
     * @param GpPedido $pedido
     * @param GpPedidoDetalle $detalle
     * @param mixed $pro
     * @return GpPedidoDetalleProveedores
     */
    public function savePedidoDetalleProveedores(GpPedido $pedido, GpPedidoDetalle $detalle, mixed $pro): GpPedidoDetalleProveedores
    {
        $proveedor = new GpPedidoDetalleProveedores;
        $proveedor->pedido_id = $pedido->id;
        $proveedor->detalle_id = $detalle->id;
        $proveedor->ID_PROVEEDOR = $pro['ID_PROVEEDOR'];
        $proveedor->DESCRIPCION_ARTICULO = trim($pro['DESCRIPCION_ARTICULO']);
        $proveedor->FECHA_ENTREGA = $pro['FECHA_ENTREGA'];
        $proveedor->CANTIDAD = (float) $pro['CANTIDAD'];
        $proveedor->COSTO_UNITARIO = (float) $pro['COSTO_UNITARIO'];
        $proveedor->IMPUESTO = (float) $pro['IMPUESTO'];
        $proveedor->TOTAL = (float) $pro['TOTAL'];
        $proveedor->TOTALCONIMPUESTO = (float) $pro['TOTALCONIMPUESTO'];
        $proveedor->NOTA = $pro['NOTA'];
        if (isset($pro['recibido'])) {
            $proveedor->recibido = $pro['recibido'];
        }
        if (isset($pro['MARGEN'])) {
            $proveedor->MARGEN = $pro['MARGEN'];
        }
        return $proveedor;
    }

    /**
     * @param $stock1
     * @param GpPedido $pedido
     * @param GpPedidoDetalle $detalle
     * @return void
     */
    public function savePedidoDetalleStock($stock1, GpPedido $pedido, GpPedidoDetalle $detalle): void
    {
        if (count($stock1) > 0) {
            foreach ($stock1 as $st) {
                $stock = new GpPedidoDetalleStock();
                $stock->pedido_id = $pedido->id;
                $stock->detalle_id = $detalle->id;
                $stock->STO_ART_BODEGA = $st['STO_ART_BODEGA'];
                $stock->STO_ART_STOCK_DISPONIBLE = $st['STO_ART_STOCK_DISPONIBLE'];
                $stock->seleccionado = $st['seleccionado'];
                $stock->reservado = $st['seleccionado'];
                $stock->save();
            }
        }
    }

    /** migrar mas adelante */

    public function volveraFacuturarTest($devolucionId)
    {
        try {
            DB::beginTransaction();
            $devolucion = GpDevoluciones::where('id', $devolucionId)->first();
            $this->volverafacturar($devolucion);
            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function volverafacturar($devolucion)
    {
        $pedido = GpPedido::where('id', $devolucion->pedido_id)->first();
        $despachada = $this->orderService->checkOutValidateDispatchReInvoice($devolucion);
        $cliente = GpCliente::where('id', $devolucion->cliente_volver_a_facturar_id)->first();
        $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();

        $nuevo_pedido = $pedido->replicate();

        $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

        $nuevo_pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);

        $nuevo_pedido->factura = null;
        $nuevo_pedido->factura_origen = $pedido->factura;
        $nuevo_pedido->fecha_factura = null;
        $nuevo_pedido->estado = 'espera';
        $nuevo_pedido->sucursal_id = $devolucion->sucursal_id;
        $nuevo_pedido->sucursal_facturacion_id = $devolucion->sucursal_facturacion_id;
        $nuevo_pedido->cliente_id = $devolucion->cliente_volver_a_facturar_id;
        $nuevo_pedido->orden_de_compra = $devolucion->orden_de_compra;
        $nuevo_pedido->nota_cliente = $devolucion->nota_cliente;

        $nuevo_pedido->CLI_RUC = $cliente->CLI_RUC;
        $nuevo_pedido->CLI_RAZON_SOCIAL = $cliente->CLI_RAZON_SOCIAL;
        $nuevo_pedido->CLI_CORREO_ELECT_SRI = $cliente->CLI_CORREO_ELECT_SRI;

        $nuevo_pedido->factura_siguiente_mes = 0;

        if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
            $nuevo_pedido->factura_posterior_despacho = 1;
            if ($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                $nuevo_pedido->factura_a_demanda = 1;
            } elseif (date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION) {
                $nuevo_pedido->factura_siguiente_mes = 1;
            }
        }

        if ($despachada) {
            $nuevo_pedido->despachado = 1;
        }

        $nuevo_pedido->save();

        foreach ($detalle as $det) {
            $nuevo_det = $det->replicate();
            $nuevo_det->pedido_id = $nuevo_pedido->id;
            $nuevo_det->devuelto = null;

            if ($devolucion->tipo_devolucion == 'parcial') {
                $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
                    ->where('devolucion_id', $devolucion->id)
                    ->first();
                if ($articulo_devolucion) {
                    if ($despachada) {  //&& $devolucion->tipo_devolucion == 'total'
                        $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible - $articulo_devolucion->recibido;
                    } else {
                        $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible - $articulo_devolucion->seleccionado;
                    }
                } else {
                    $nuevo_det->ART_CANTIDAD = null;
                }
            } else {
                $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
                    ->where('devolucion_id', $devolucion->id)
                    ->first();
                if ($articulo_devolucion) {
                    $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible;
                } else {
                    $nuevo_det->ART_CANTIDAD = null;
                }
            }

            if (!$nuevo_det->ART_CANTIDAD)
                continue;

            $nuevo_det->ART_IMP_VTA = GpArticulo::where('ART_CODIGO', $nuevo_det->ART_CODIGO)->first()->ART_IMP_VTA;

            $nuevo_det->save();

            $detalle_proveedores = GpPedidoDetalleProveedores::where('pedido_id', $pedido->id)
                ->where('detalle_id', $det->id)
                ->get();

            foreach ($detalle_proveedores as $proveedor) {
                $nuevo_proveedor = $proveedor->replicate();
                $nuevo_proveedor->pedido_id = $nuevo_pedido->id;
                $nuevo_proveedor->detalle_id = $nuevo_det->id;
                $nuevo_proveedor->save();

                $parameters = array(
                    // 'IDCompania' => env('ID_COMPANIA'),
                    'fecha' => date('Y/m/d'),
                    'item' => $nuevo_det->ART_CODIGO,
                    'bodega' => $nuevo_pedido->BOD_BODEGA,
                    'cantidad' => $nuevo_proveedor->CANTIDAD,
                    'BinOrigen' => $pedido->pedido,
                    'BinDestino' => $nuevo_pedido->pedido,
                );
                // $webservices->inventario('TransferenciaCompartimento', $parameters);
                $this->apiGp->transferCompartment($parameters);
            }

            $detalle_stocks = GpPedidoDetalleStock::where('pedido_id', $pedido->id)
                ->where('detalle_id', $det->id)
                ->get();

            foreach ($detalle_stocks as $stock) {
                $nuevo_stock = $stock->replicate();
                $nuevo_stock->pedido_id = $nuevo_pedido->id;
                $nuevo_stock->detalle_id = $nuevo_det->id;

                if ($devolucion->tipo_devolucion == 'parcial' && $nuevo_stock->STO_ART_BODEGA == $pedido->BOD_BODEGA) {
                    $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
                        ->where('devolucion_id', $devolucion->id)
                        ->first();
                    if ($articulo_devolucion) {
                        $nuevo_stock->seleccionado = $articulo_devolucion->disponible - $articulo_devolucion->seleccionado;
                        $nuevo_stock->reservado = $nuevo_stock->seleccionado;
                    }
                } elseif ($devolucion->tipo_devolucion == 'total') {
                    $nuevo_stock->reservado = $nuevo_stock->seleccionado;
                }
                $nuevo_stock->save();
            }
        }

        // $funciones = new FuncionesController();
        // $funciones->recalcularpedido($nuevo_pedido);
        $this->functionService->recalculateOrder($nuevo_pedido->id);


        // $this->checkenviarfactura($nuevo_pedido);
        $this->invoiceService->checkSendInvoice($nuevo_pedido);
    }

    function cancelarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = GpUsuario::where('id', Auth::user()->id)->first();

        $pedido = GpPedido::where('id', $data['id'])->get()->first();

        if ($pedido->estado == 'facturado') {
            return response([
                'success' => true,
                'message' => 'Pedido ' . $pedido->pedido . ' ya está facturado y no se puede eliminar',
            ]);
        }

        $pedido->estado = 'eliminado';
        $pedido->USERID_cancelado = $user->USERID;
        $pedido->fecha_hora_cancelado = date('Y-m-d H:i:s');
        $pedido->save();

        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        GpOrdenDespacho::where('pedido_id', $pedido->id)->update(['estado' => 'cancelada']);

        return response([
            'success' => true,
            'message' => 'Pedido ' . $pedido->pedido . ' eliminado',
        ]);
    }

    public function recalcularpedidourl()
    {
        $pedido = GpPedido::where('id', 115242)->get()->first();

        $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();
        $subtotal = 0;
        $descuento = 0;
        $subtotalmenosdescuento = 0;
        $iva = 0;
        foreach ($detalle as $articulo) {
            $subtotal += $articulo->ART_VALOR * $articulo->ART_CANTIDAD;
            $descuento += number_format($articulo->ART_DESCUENTO * $articulo->ART_CANTIDAD, 2, '.', '');
            $subtotalmenosdescuento += number_format(($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD, 2, '.', '');
            $iva += number_format((($articulo->ART_VALOR - ($articulo->ART_VALOR * ($articulo->descuento / 100))) * $articulo->ART_CANTIDAD) * ($articulo->ART_IMP_VTA / 100), 2, '.', '');
        }
        $subtotalmenosdescuento = number_format($subtotalmenosdescuento, 2, '.', '');
        $iva = number_format($iva, 2, '.', '');
        $total = $subtotalmenosdescuento + $iva;

        $pedido->subtotal = $subtotal;
        $pedido->descuento = $descuento;
        $pedido->subtotalmenosdescuento = $subtotalmenosdescuento;
        $pedido->iva = $iva;
        $pedido->total = $total;


        $pedido->save();
    }


    public function getListPedidosSinCoordenadas()
    {
        return $this->orderService->getLisOrdersWithoutCoordinated();
    }


    public function buscarIteml($artCode)
    {
        $item = GpArticulo::where('ART_CODIGO', $artCode)->first()->toArray();
        return $this->orderService->buscarItemlTest($item);
    }

    public function generarOrdenDeDespacho($id)
    {
        return $this->orderService->generateOrderDispatch($id);
    }


    public function recalcularPedidoTest($id)
    {

        $pedido = GpPedido::where('id', $id)->get()->first();
        $this->functionService->recalculateOrder($pedido->id);
    }


    public function facturarsiguientemes()
    {
        try {
            $resultado = $this->orderService->generateNextMonthInvoice();
            if ($resultado['abortado']) {
                return $this->buildSuccessResponse($resultado['razon'], 200);
            }
            // ✅ Manejar errores parciales
            if ($resultado['fallidos'] > 0) {
                return $this->buildSuccessResponse(
                    "Proceso completado con errores: {$resultado['exitosos']} exitosos, {$resultado['fallidos']} fallidos",
                    200,
                );
            }
            return $this->buildSuccessResponse(
                "Todos los pedidos facturados: {$resultado['exitosos']} procesados",
                200,
            );
        } catch (\Throwable $e) {
            Log::channel('cron_process')->error("❌ Error crítico en proceso de facturación masiva", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->buildErrorResponse(
                "Error crítico en el proceso: {$e->getMessage()}",
                500
            );
        }
    }


    /** funcion para actualizar un pedido  */

    public function actualizarPedidoYRecalcular($id)
    {
        return $this->orderService->updateOrderAndRecalculate($id);
    }

    /** funcion para el cambio de orden de compra y nota cliente por pediod */
    public function actualizarOrdenCompraYNotaCliente(Request $request)
    {
        $id = $request->input('id');
        $newOrd = $request->input('orden_de_compra');
        $newNote = $request->input('nota_cliente');
        try {
            $data = $this->orderService->syncPurchaseOrderAndNoteByOrder($id, $newOrd, $newNote);
            return $this->successResponse('Datos actualizados Correctamente', $data, 200);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar los datos del pedido: ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->buildErrorResponse('Opps! Error al actualizar los datos del pedido', 500);
        }
    }


    public function actualizarFechaReservaPedido(Request $request)
    {

        $validate = $request->validate([
            'id' => 'required|integer',
            'fe_facturacion_reserva' => 'required|date_format:Y-m-d',
        ]);

        $id = data_get($validate, 'id');
        $newDate = data_get($validate, 'fe_facturacion_reserva');
        try {
            $data = $this->orderService->updateOrderReservationDate($id, $newDate);
            return $this->successResponse('Fecha de reserva actualizada Correctamente', $data, 200);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar la fecha de reserva del pedido: ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->buildErrorResponse('Opps! Error al actualizar la fecha de reserva del pedido', 500);
        }
    }



    /** funcion de lisras de pedido reservas  */


    public function listarPedidosReservas(Request $request): JsonResponse
    {

        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'desc');
        $ptoVtaId = $request->input('ptoVtaId', null);

        try {
            $data = $this->orderService->fetchOrderFromReservations($perPage, $search, $sortBy, $sortOrder, $ptoVtaId);
            return $this->successResponse('Lista de pedidos de reservas obtenida correctamente', $data, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function actualizarBloqueoPendienteDespacho(Request $request)
    {

        try {
            $id = $request->input('id');
            $pendingDispach = $request->input('pendiente_salida');
            $order = $this->orderService->updateOrderAndBlockDispach($id, $pendingDispach);
            return $this->successResponse('Bloque actualizado correctamente', $order, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);

        }
    }
}
