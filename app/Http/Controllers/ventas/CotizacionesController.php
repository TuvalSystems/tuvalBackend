<?php

namespace App\Http\Controllers\ventas;

use App\Enums\Ventas\TaxRateEnum;
use App\Http\Controllers\Controller;
use App\Models\GpCronLog;
use App\Models\GpProspecto;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\GpTempCart;
use App\Models\GpTempCartStock;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpClienteSucursal;
use App\Models\GpVendedor;
use App\Models\GpCliente;
use App\Models\GpDescuentoFormaPago;
use Illuminate\Http\Request;
use App\Http\Controllers\WebserviceController;
use App\Http\Controllers\FuncionesController;
use App\Http\Requests\Venta\Cotizaciones\ValidateStoreQuotationRequest;
use App\Models\GpPedido;
use App\Models\GpUsuario;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use App\Services\Venta\Contracts\QuotationCreatorServiceInterface;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CotizacionesController extends Controller
{
    use JsonResponseTrait;

    public function __construct(
        protected readonly QuotationServiceInterface $quotationService,
        protected readonly ClientQueryServiceInterface $clientService,
        protected readonly QuotationCreatorServiceInterface $quotationCreator
    ) {}




    public function store(ValidateStoreQuotationRequest $request): JsonResponse
    {

        try {
            $imagenesExistentes = $request->input('imagenes_existentes', []);
            $imagenesNuevas = $request->file('imagenes_nuevas', []);
            $cotizacion = $this->quotationCreator->createOrUpdate(
                $request->validated(),
                Auth::user(),
                $imagenesExistentes,
                $imagenesNuevas
            );
            $msg = "Cotización {$cotizacion->cotizacion} guardada correctamente";
            return $this->buildSuccessResponse($msg, 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Error in CotizacionesController@store', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'file'  => $e->getFile(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }
    public function selectarticulo(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->validate([
            'cliente_id' => '',
            'puntodeventa_id' => '',
            'sucursal_id' => '',
            'articulo' => []
        ]);

        $user = GpUsuario::where('id', Auth::user()->id)->first();

        if ($data['articulo']['ART_CLASE_ARTICULO'] == 'VTA-SERV') {
            $data['articulo']['ART_TIPO_COMPRA'] = 'SERV';
        }

        if ($data['articulo']['ART_CLASE_ARTICULO'] == 'VTA-SERV-L')
            $data['articulo']['ART_TIPO_COMPRA'] = 'L';

        $articulo = new GpTempCart;
        $articulo->sucursal_id = $data['sucursal_id'];
        $articulo->cliente_id = $data['cliente_id'];
        $articulo->puntodeventa_id = $data['puntodeventa_id'];
        $articulo->user_id = $user->id;
        $articulo->USERID = $user->USERID;
        $articulo->ART_CODIGO = $data['articulo']['ART_CODIGO'];
        $articulo->ART_DESCRIPCION = trim(str_replace('  ', ' ', $data['articulo']['ART_DESCRIPCION']));
        $articulo->ART_DESCRIPCION_ORIGINAL = trim(str_replace('  ', ' ', $data['articulo']['ART_DESCRIPCION_ORIGINAL']));
        // $articulo->CHECK_ALTERNATIVO = $data['articulo']['CHECK_ALTERNATIVO'] ?? 0; // no va pero igual va comentado por si acaso
        $articulo->ART_CODIGO_TRES_LETRAS = $data['articulo']['ART_CODIGO_TRES_LETRAS'];
        $articulo->ART_DECIMAL_CANTIDAD = $data['articulo']['ART_DECIMAL_CANTIDAD'];
        $articulo->ART_PLAN_UMEDIDA = $data['articulo']['ART_PLAN_UMEDIDA'];
        $articulo->ART_MARCA = $data['articulo']['ART_MARCA'];
        $articulo->ART_MATERIAL = $data['articulo']['ART_MATERIAL'];
        $articulo->ART_PESO = $data['articulo']['ART_PESO'];
        $articulo->ART_TIPO_COMPRA = $data['articulo']['ART_TIPO_COMPRA'];
        $articulo->ART_VALOR = $data['articulo']['ART_VALOR'];
        $articulo->ART_VALOR_ORIGINAL = $data['articulo']['ART_VALOR_ORIGINAL'];
        $articulo->ART_CANTIDAD = 0;
        $articulo->ART_IVA = 0;
        $articulo->ART_DESCUENTO = 0;
        $articulo->ART_STOCK = $data['articulo']['ART_STOCK'];
        $articulo->ART_IMP_VTA = $data['articulo']['ART_IMP_VTA'];
        $articulo->ART_COSTO_PROMEDIO = $data['articulo']['ART_COSTO_PROMEDIO'];
        $articulo->descuento = $data['articulo']['descuento'];
        $articulo->descuento_original = $data['articulo']['descuento_original'];
        $articulo->modificador = $data['articulo']['modificador'];
        $orden = GpTempCart::where('sucursal_id', $data['sucursal_id'])
            ->where('cliente_id', $data['cliente_id'])
            ->where('puntodeventa_id', $data['puntodeventa_id'])
            ->orderBy('orden', 'DESC')
            ->first();
        if (!$orden) {
            $orden = new \stdClass();
            $orden->orden = 0;
        }
        $articulo->orden = $orden->orden + 1;
        $articulo->save();

        $array_stock = array();

        foreach ($data['articulo']['stock'] as $stockbodega) {
            $stock = new GpTempCartStock;
            $stock->id_tempcart = $articulo->id;
            $stock->STO_ART_BODEGA = $stockbodega['STO_ART_BODEGA'];
            $stock->STO_ART_STOCK_DISPONIBLE = $stockbodega['STO_ART_STOCK_DISPONIBLE'];
            $stock->seleccionado = 0;
            $stock->save();

            $array_stock[] = $stock;
        }

        $articulo->proveedores = array();

        $articulo->stock = $array_stock;

        $imagenes = array();
        for ($i = 1; $i <= 3; $i++) {
            $file = 'images/productos/' . $articulo->ART_CODIGO_TRES_LETRAS . '_' . $i . '.jpg';

            if (File::exists($file)) {
                $imagenes[] = env('APP_URL') . '/' . $file;
            }
        }

        $articulo->imagenes = $imagenes;

        return response([
            'success' => true,
            'articulo' => $articulo,
        ]);
    }



    public function selectarticuloprospecto(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->validate([

            'articulo' => []
        ]);

        $user = Auth::user();

        if ($data['articulo']['ART_CLASE_ARTICULO'] == 'VTA-SERV') {
            $data['articulo']['ART_TIPO_COMPRA'] = 'SERV';
        }

        $data['articulo']['timestamp'] = time();

        return response([
            'success' => true,
            'articulo' => $data['articulo'],
        ]);
    }





    public function guardarcotizacionprospecto(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = GpUsuario::where('id', Auth::user()->id)->first();
        if (isset($data['prospecto']['id']) && $data['prospecto']['id'] != '') {
            $prospecto = GpProspecto::where('id', $data['prospecto']['id'])->first();
        } else {
            $prospecto = new GpProspecto;
        }
        $prospecto->CLI_RUC = trim($data['prospecto']['CLI_RUC']);
        $prospecto->CLI_RAZON_SOCIAL = trim($data['prospecto']['CLI_RAZON_SOCIAL']);
        $prospecto->CLI_CORREO_ELECT_SRI = trim($data['prospecto']['CLI_CORREO_ELECT_SRI']);
        $prospecto->CLI_DIRECCION = trim($data['prospecto']['CLI_DIRECCION']);
        $prospecto->CLI_TELEFONO_1 = trim($data['prospecto']['CLI_TELEFONO_1']);
        $prospecto->save();

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $cotizacion = new GpCotizacion;

        $cotizacion->user_id = $user->id;
        $cotizacion->sucursal_id = $data['sucursal_id'];
        $cotizacion->sucursal_facturacion_id = $data['sucursal_facturacion_id'];
        $cotizacion->cliente_id = $data['cliente_id'];
        $cotizacion->prospecto_id = $prospecto->id;
        $cotizacion->estado = 'aprobado';
        $cotizacion->factura_posterior_despacho = $data['factura_posterior_despacho'];
        $cotizacion->factura_a_demanda = $data['factura_a_demanda'];
        $cotizacion->factura_siguiente_mes = $data['factura_siguiente_mes'];
        $cotizacion->BOD_BODEGA = $data['bodega_facturacion'];
        $cotizacion->PTO_VTA_ID = $data['PTO_VTA_ID'];
        $cotizacion->PTO_VTA_DESCRIPCION = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $cotizacion->USERID = $user->USERID;
        $cotizacion->DESC_FORMA_PAGO_CODIGO = $data['DESC_FORMA_PAGO_CODIGO'];
        $cotizacion->CLI_RUC = $data['CLI_RUC'];
        $cotizacion->CLI_RAZON_SOCIAL = $data['CLI_RAZON_SOCIAL'];
        $cotizacion->CLI_CORREO_ELECT_SRI = $data['CLI_CORREO_ELECT_SRI'];
        $cotizacion->COND_ID = $data['COND_ID'];
        $cotizacion->COND_ID_ORIGINAL = $data['COND_ID_ORIGINAL'];
        $cotizacion->VEND_ID = $data['VEND_ID'];
        $cotizacion->VEND_NOMBRES = $data['VEND_NOMBRES'];
        $cotizacion->VEND_APELLIDOS = $data['VEND_APELLIDOS'];
        $cotizacion->despacho = $data['despacho'];
        $cotizacion->porcentage_descuentooriginal = $data['porcentage_descuentooriginal'];
        $cotizacion->porcentage_descuento = $data['porcentage_descuento'];

        $cotizacion->subtotal = (float) $data['subtotal'] + (float) $data['descuento'];
        $cotizacion->descuento = (float) $data['descuento'];
        $cotizacion->subtotalmenosdescuento = (float) $data['subtotal'];
        $cotizacion->iva = (float) $data['iva'];
        $cotizacion->total = (float) $data['total'];
        $cotizacion->requiere_aprobacion_comercial = 'no';
        $cotizacion->aprobado_comercial = 'no';
        $cotizacion->aprobador_comercial_id = null;
        $cotizacion->requiere_aprobacion_crediticia = 'no';
        $cotizacion->aprobado_crediticio = 'no';
        $cotizacion->aprobador_crediticio_id = null;
        $cotizacion->impreso = 'no';
        $cotizacion->nota_interna = $data['nota_interna'];
        $cotizacion->nota_cliente = $data['nota_cliente'];
        $cotizacion->orden_de_compra = $data['orden_de_compra'];
        $cotizacion->impuesto = TaxRateEnum::IVA_15->value;

        if ($cotizacion->save()) {

            foreach ($data['cotizacion'] as $det) {
                $detalle = new GpCotizacionDetalle;
                $detalle->cotizacion_id = $cotizacion->id;
                $detalle->ART_CODIGO = $det['ART_CODIGO'];
                $detalle->ART_DESCRIPCION = $det['ART_DESCRIPCION'];
                $detalle->ART_DESCRIPCION_ORIGINAL = $det['ART_DESCRIPCION_ORIGINAL'];
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
                $detalle->ART_STOCK = (float)data_get( $det, 'ART_STOCK', 0);
                $detalle->ART_IMP_VTA = (float) $det['ART_IMP_VTA'];
                $detalle->ART_COSTO_PROMEDIO = (float) $det['ART_COSTO_PROMEDIO'];
                $detalle->descuento = (float) $det['descuento'];
                $detalle->descuento_original = (float) $det['descuento_original'];
                $detalle->modificador = (float) $det['modificador'];
                $detalle->save();
            }

            return response([
                'success' => true,
                'message' => 'La cotización ha sido guardada'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 409);
        }
    }

    public function vercotizaciones(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'puntodeventa_id' => 'required',
            'estado' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ]);

        $cotizaciones = GpCotizacion::with('reserva')->where('gp_cotizacions.PTO_VTA_ID', '=', $data['puntodeventa_id'])
            ->join('gp_descuento_forma_pagos', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_CODIGO', 'gp_cotizacions.DESC_FORMA_PAGO_CODIGO')
            ->select('gp_cotizacions.*', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_TIPO_CREDITO');

        if ($data['estado'] == 'pendientes') {
            $cotizaciones = $cotizaciones
                ->where(function ($q) {
                    $q->where('estado', '!=', 'procesado');
                    $q->where('estado', '!=', 'caducado');
                    $q->where('estado', '!=', 'rechazado');
                })
                ->where(function ($q) {
                    $q->whereNull('prospecto_id');
                    $q->orWhere(function ($q) {
                        $q->whereNotNull('prospecto_id');
                        $q->whereNotNull('cliente_id');
                    });
                });
        } else if ($data['estado'] == 'caducado') {
            $cotizaciones = $cotizaciones->where('estado', 'caducado')
                ->whereNull('prospecto_id');
        } else if ($data['estado'] == 'prospectos') {
            $cotizaciones = $cotizaciones
                ->where(function ($q) {
                    $q->whereNotNull('prospecto_id');
                    $q->whereNull('cliente_id');
                })
                ->where('estado', '!=', 'procesado');
        } else if ($data['estado'] == 'procesadas') {
            $cotizaciones = $cotizaciones->where('estado', 'procesado');
        } else if ($data['estado'] == 'rechazadas') {
            $cotizaciones = $cotizaciones->where('estado', 'rechazado');
        }
        if (isset($data['fecha_desde']) && $data['fecha_desde'] != '' && isset($data['fecha_hasta']) && $data['fecha_hasta'] != '') {
            $cotizaciones = $cotizaciones->whereDate('gp_cotizacions.created_at', '>=', $data['fecha_desde'] . ' 00:00:00');
            $cotizaciones = $cotizaciones->whereDate('gp_cotizacions.created_at', '<=', $data['fecha_hasta'] . ' 23:59:59');
        }
        $cotizaciones = $cotizaciones->orderBy('created_at', 'desc')->limit(500)->get();



        foreach ($cotizaciones as $cotizacion) {

            $cotizacion->direccion_entrega = GpClienteSucursal::where('id', $cotizacion->sucursal_id)->first();
            $cotizacion->recordatorioventa = GpPedido::where('cotizacion_id', $cotizacion->id)->whereNotNull('factura')->exists() ? 1 : 0;
            $cotizacion->recuperar = 'yes';
            $cotizacion->imprimir = 'yes';
            $cotizacion->facturar = 'no';
            if ($cotizacion->requiere_aprobacion_comercial == 'yes' && $cotizacion->aprobado_comercial == 'no') {
                $cotizacion->imprimir = 'no';
            } elseif ($cotizacion->requiere_aprobacion_crediticia == 'yes' && $cotizacion->aprobado_crediticio == 'no') {
                $cotizacion->imprimir = 'no';
                $cotizacion->recuperar = 'no';
            }
            if ($cotizacion->impreso == 'yes') {
                $cotizacion->recuperar = 'yes';
                $cotizacion->facturar = 'yes';
            }
            if ($cotizacion->estado == 'rechazado') {
                $cotizacion->imprimir = 'no';
                $cotizacion->recuperar = 'no';
            }
        }
        return response([
            'success' => true,
            'cotizaciones' => $cotizaciones,
        ]);
    }
    public function guardarordenpedido(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $item) {
            GpTempCart::where('id', $item['id'])
                ->update(['orden' => $key + 1]);
        }
    }

    public function guardardireccion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        foreach ($data as $key => $direccion) {
            if ($key != 'CLI_SUC_CORREO_ELECTRONICO_SRI')
                $data[$key] = mb_strtoupper($direccion);
            else
                $data[$key] = strtolower($direccion);
        }
        $data['CLI_SUC_PROVINCIA'] = $data['CLI_SUC_PROVINCIA_NOMBRE'];
        unset($data['CLI_SUC_PROVINCIA_NOMBRE']);

        $dir = str_split($data['CLI_SUC_DIRECCION'], 61);

        if (!isset($dir[1])) {
            $dir[1] = '';
        }

        $parameters = array(
            'CLI_DIR_RUC' => $data['CLI_SUC_RUC'],
            'CLI_DIR_CONTACTO' => $data['CLI_SUC_CONTACTO'],
            'CLI_DIR_ID_DIRECCION' => '',
            'CLI_DIR_DIRECCION_1' => $dir[0],
            'CLI_DIR_DIRECCION_2' => $dir[1],
            'CLI_DIR_CIUDAD' => $data['CLI_SUC_CIUDAD'],
            'CLI_DIR_PROVINCIA' => $data['CLI_SUC_PROVINCIA'],
            'CLI_DIR_PAIS' => 'Ec',
            'CLI_DIR_TERRITORIO' => $data['CLI_SUC_TERRITORIO'],
            'CLI_DIR_TELEFONO_1' => $data['CLI_SUC_TELEFONO'],
            'CLI_DIR_TELEFONO_2' => '',
            'CLI_DIR_TELEFONO_3' => '',
            'CLI_DIR_TELEFONO_4' => '',
            'CLI_DIR_CORREO_ELECTRONICO_SRI' => $data['CLI_SUC_CORREO_ELECTRONICO_SRI'],
            'CLI_DIR_CORREO_ELECTRONICO_NOTIFICACION_INTERNA' => $data['CLI_SUC_CORREO_ELECTRONICO_SRI'],
            'CLI_DIR_VENDEDOR' => $data['CLI_SUC_VENDEDOR'],
            'CLI_DIR_PUNTO_VENTA' => $data['CLI_DIR_PUNTO_VENTA'],
        );


        $webservices = new WebserviceController;
        $parameters = [
            'Direccion_Cliente' => array($parameters)
        ];

        $nuevaDireccion = $webservices->transaccional('MST_Direccion_Cliente_Creacion_Modificacion', $parameters);
        Log::info('Nueva Direccion', $nuevaDireccion);
        if (!isset($nuevaDireccion['MST_Direccion_Cliente_Creacion_ModificacionResult'])) {

            return response([
                'error' => 'Hubo un error al guardar'
            ], 409);
        }

        //        echo '<pre>';
        //        print_r($nuevaDireccion);

        $CLI_SUC_ID_DIRECCION = explode('-', $nuevaDireccion['MST_Direccion_Cliente_Creacion_ModificacionResult']);

        if (!isset($CLI_SUC_ID_DIRECCION[1])) {
            return response([
                'error' => $nuevaDireccion['MST_Direccion_Cliente_Creacion_ModificacionResult']
            ], 409);
        }


        $direccion = new GpClienteSucursal;
        $direccion->CLI_SUC_RUC = $data['CLI_SUC_RUC'];
        $direccion->CLI_SUC_DESCRIPCION = $data['CLI_SUC_DESCRIPCION'];
        $direccion->CLI_SUC_ID_DIRECCION = $CLI_SUC_ID_DIRECCION[1];
        $direccion->CLI_SUC_DIRECCION = $data['CLI_SUC_DIRECCION'];
        $direccion->CLI_SUC_CONTACTO = $data['CLI_SUC_CONTACTO'];
        $direccion->CLI_SUC_REFERENCIA = $data['CLI_SUC_REFERENCIA'];
        $direccion->CLI_SUC_VENDEDOR = $data['CLI_SUC_VENDEDOR'];
        $direccion->CLI_SUC_TERRITORIO = $data['CLI_SUC_TERRITORIO'];
        $direccion->CLI_SUC_CIUDAD = $data['CLI_SUC_CIUDAD'];
        $direccion->CLI_SUC_PROVINCIA = $data['CLI_SUC_PROVINCIA'];
        $direccion->CLI_SUC_PAIS = 'Ecuador';
        $direccion->CLI_SUC_TELEFONO = $data['CLI_SUC_TELEFONO'];
        $direccion->CLI_SUC_CORREO_ELECTRONICO_SRI = $data['CLI_SUC_CORREO_ELECTRONICO_SRI'];
        $direccion->CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA = $data['CLI_SUC_CORREO_ELECTRONICO_SRI'];


        if ($direccion->save()) {

            $cliente = GpCliente::where('CLI_RUC', $data['CLI_SUC_RUC'])->get()->first();
            $vendedor = GpVendedor::where('VEND_ID', $cliente->CLI_VENDEDOR)->first();
            $cliente->vendedor = $vendedor;

            $sucursales = GpClienteSucursal::where('CLI_SUC_RUC', $cliente->CLI_RUC)->get();
            $cliente->sucursales = $sucursales;
            $cliente->sucursalesfacturacion = $sucursales;

            return response([
                'success' => true,
                'message' => 'Dirección de cliente guardado',
                'cliente' => $cliente,
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 409);
        }
    }

    public function guardarcliente(Request $request)
    {
        try {
            $data = $request->all();
            $user = GpUsuario::where('id', Auth::user()->id)->first();
            $cliente = DB::transaction(fn() => $this->quotationService->storeClientQuotation($data, $user));
            return $this->successResponse('Información Obtenida Correctamente', $cliente, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function guardarNuevaDireccionSucursal(Request $request)
    {
        try {
            $data = data_get($request->all(), 'form', []);
            $sucursal = $this->clientService->storeNewAddressClient($data);
            return $this->successResponse('Información Obtenida Correctamente', $sucursal, 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {

            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function detallecotizacion(Request $request)
    {
        $data = $request->all();
        try {
            $quote = $this->quotationService->detailQuotation($data);
            return $this->successResponse('Información Obtenida Correctamente', $quote, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    /** proceso de recuperar cotizacion optimizado para el nuevo front  */

    public function recuperarCotizacionV2(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|exists:gp_cotizacions,id',
            ]);
            $cotizacion = $this->quotationService->restoreQuotationV2($request->input('id'));
            return $this->successResponse('Información Obtenida Correctamente', $cotizacion, 200);
        } catch (ModelNotFoundException $e) {
            return $this->buildErrorResponse("La cotización no existe.", 404);
        } catch (\Throwable $th) {
            Log::error('Error in recuperarCotizacionV2 (Recuperar cotización V2)', [
                'error' => $th->getMessage(),
                'linea' => $th->getLine(),
                'file' => $th->getFile(),
            ]);
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function recuperarcotizacionprospecto(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $cliente = GpProspecto::where('id', $data['prospecto_id'])->first();

        $tipo = 'prospecto';

        $existente = GpCliente::where('CLI_RUC', $cliente->CLI_RUC)->first();
        if ($existente) {
            $tipo = 'cliente';
            $cliente = $existente;
        }
        return response([
            'success' => true,
            'cliente' => $cliente,
            'cotizacion_id' => $data['id'],
            'tipo' => $tipo,
        ]);
    }


    public function volverAPedirCotizacionV2(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|exists:gp_cotizacions,id',
            ]);
            $info = $this->quotationService->loadQuotationForCopy($request->input('id'));
            return $this->successResponse('Información Obtenida Correctamente', $info, 200);
        } catch (ModelNotFoundException $e) {
            return $this->buildErrorResponse("La cotización no existe.", 404);
        } catch (\Throwable $th) {
            Log::error('Error in volverAPedirCotizacionV2 (Volver a pedir cotización V2)', [
                'error' => $th->getMessage(),
                'linea' => $th->getLine(),
                'file' => $th->getFile(),
            ]);
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function llenarcotizacionprospecto(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = Auth::user();

        $funciones = new FuncionesController();
        $cliente = $funciones->getcliente($data);
        $arr_bodegas = $funciones->gebodegas($data, 1);
        $consulta_bodega = array();
        $consulta_precio = array();
        $stock_bodega_arr = array();
        $arrArticulos = array();
        $cotizacion = GpCotizacion::where('id', $data['id'])->first();

        $detalle = GpCotizacionDetalle::where('cotizacion_id', $data['id'])->get();

        foreach ($detalle as $articulo) {
            $articulo->proveedores = array();

            $stockbodega = GpCotizacionDetalleStock::where('cotizacion_id', $data['id'])
                ->where('detalle_id', $articulo->id)
                ->get()->toArray();
            $stock_bodega_arr[$articulo->ART_CODIGO] = $stockbodega;


            $arrArticulos[] = array(
                'articulo' => $articulo->ART_CODIGO,
            );
            $consulta_precio[] = array(
                'ART_CODIGO' => $articulo->ART_CODIGO,
            );
        }

        $webservices = new WebserviceController;
        $precios = $webservices->listaprecios($consulta_precio);
        $lista_precios_general = array();
        foreach ($precios as $precio) {
            $lista_precios_general[$precio['LST_PRC_ARTICULO']][$precio['LST_PRC_ID']] = $precio;
        }

        // $stock_general = $funciones->stockarticulobodega($consulta_bodega, $arr_bodegas);
        $stock_general = $this->quotationService->getStockGeneralRestoreQuotation($arrArticulos, $arr_bodegas);
        $stock_general = collect($stock_general)->groupBy('STO_ART_ARTICULO') // Agrupamos primero por artículo
            ->map(function ($items) {
                return $items->keyBy('STO_ART_BODEGA'); // Agrupamos dentro por bodega
            })
            ->toArray();

        foreach ($stock_bodega_arr as $key => $bodegas) {
            foreach ($bodegas as $bodega) {
                $stock_general[$key][$bodega['STO_ART_BODEGA']]['seleccionado'] = $bodega['seleccionado'];
            }
        }

        foreach ($detalle as $articulo) {
            $funciones->formatStock($articulo, $stock_general);
            if ($articulo->ART_TIPO_COMPRA != 'L') {
                $articulo->ART_VALOR = $lista_precios_general[$articulo->ART_CODIGO][$cliente->CLI_NIVEL_PRECIO]['LST_PRC_VALOR'];
                $articulo->ART_VALOR_ORIGINAL = $lista_precios_general[$articulo->ART_CODIGO][$cliente->CLI_NIVEL_PRECIO]['LST_PRC_VALOR'];
            }
        }

        $data['sucursal_id'] = $cliente->sucursales[0]->id;
        $funciones->recalculariva($detalle);
        $funciones->setTempCartFrom($detalle, $data, $user);

        return response([
            'success' => true,
            'cliente' => $cliente,
            'currentSucursal' => $cliente->sucursales[0]->id,
            'cotizacion' => $cotizacion,
        ]);
    }

    public function limpiarcotizacion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->validate([
            'cliente_id' => '',
            'puntodeventa_id' => '',
            'sucursal_id' => '',
        ]);

        $user = Auth::user();

        GpTempCart::where('sucursal_id', $data['sucursal_id'])
            ->where('puntodeventa_id', $data['puntodeventa_id'])
            ->where('cliente_id', $data['cliente_id'])
            ->where('user_id', $user->id)
            ->delete();

        return response([
            'success' => true,
        ]);
    }


    /*

     * ELIMINA LAS COTIZACIONES EXPIRADAS SEGÚN LOS DIAS POR PUNTO DE VENTA
     *
     *
     */

    public function checkvalidezcotizaciones()
    {

        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');

        //DB::statement("OPTIMIZE TABLE gp_temp_carts;");

        $puntosdeventa = GpPuntoVenta::get();

        foreach ($puntosdeventa as $puntodeventa) {


            $cotizaciones = GpCotizacion::where('PTO_VTA_ID', $puntodeventa->PTO_VTA_ID)
                ->where('estado', '!=', 'caducado')
                ->where('estado', '!=', 'procesado')
                //->where('impreso', 'yes')
                ->orderBy('created_at')
                ->get();

            if (count($cotizaciones)) {

                //                echo '<pre>';
                //                print_r($cotizaciones);
                //                die();

                foreach ($cotizaciones as $cotizacion) {
                    $validez_real = $puntodeventa->PTO_VTA_DIA_VALIDEZ_COTIZACION;
                    for ($i = 1; $i <= $validez_real; $i++) {
                        $expira = date('Y-m-d', strtotime($i . ' day', strtotime($cotizacion->created_at)));
                        $parameters = array(
                            'IDCompania' => env('ID_COMPANIA'),
                            'Fecha' => $expira,
                        );
                        $webservices = new WebserviceController;
                        $diaHabil = $webservices->cobranzas('ConsultaDiaFeriado', $parameters);
                        if ($diaHabil['ConsultaDiaFeriadoResult']['Retorno']['0']['Codigo'] == 0) {
                            $validez_real++;
                        }
                    }
                    $expira = date('Y-m-d', strtotime($validez_real . ' day', strtotime($cotizacion->created_at)));

                    if ($expira < date('Y-m-d')) {
                        $cotizacion->estado = 'caducado';
                        $cotizacion->save();
                    }
                    //                    echo '<pre>';
                    //
                    //                    echo $expira.'<br>';
                    //                    echo $cotizacion->created_at.'<br>';
                    //                    print_r($cotizacion);
                    //                    die();
                }
            }
        }
        $cronLog = new GpCronLog();
        $cronLog->metodo = 'checkvalidezcotizaciones';
        $cronLog->fecha = date('Y-m-d H:i:s');
        $cronLog->save();
    }

    public function imprimircotizacion($id)
    {
        try {
            $cotizacion = $this->quotationService->printQuotationPdf($id, false);
            $pdf = Pdf::loadView('cotizacionPDF', $cotizacion);
            return $pdf->stream('cotizacion-' . $cotizacion['cotizacion'] . '.pdf');
        } catch (HttpException $e) {
            return $this->buildErrorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function imprimircotizacionimagenes($id)
    {
        try {
            $cotizacion = $this->quotationService->printQuotationPdf($id, true);
            $pdf = Pdf::loadView('cotizacionImagenesPDF', $cotizacion);
            return $pdf->stream('cotizacion-' . $cotizacion['cotizacion'] . '.pdf');
        } catch (HttpException $e) {
            return $this->buildErrorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function imprimircotizacionprospecto($id): \Illuminate\Http\Response|string
    {
        $cotizacion = GpCotizacion::where('id', $id)->first()->toarray();


        GpCotizacion::where('id', $id)->update(['impreso' => 'yes']);

        GpCotizacion::where('id', $id)
            ->whereNull('fecha_impreso')
            ->update(['fecha_impreso' => date('Y-m-d H:i:s')]);

        $detalle = GpCotizacionDetalle::where('cotizacion_id', $cotizacion['id'])->get()->toarray();
        $peso = 0;
        foreach ($detalle as &$art) {
            $peso += $art['ART_PESO'] * $art['ART_CANTIDAD'];
        }
        $cotizacion['peso'] = $peso;
        $cotizacion['detalle'] = $detalle;
        $formadepago = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $cotizacion['DESC_FORMA_PAGO_CODIGO'])->first()->toarray();
        $cotizacion['formadepago'] = $formadepago['DESC_FORMA_PAGO_TIPO_CREDITO'];

        $cliente = GpProspecto::where('id', $cotizacion['prospecto_id'])->first()->toarray();

        $cotizacion['cliente'] = $cliente;
        //
        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $cotizacion['PTO_VTA_ID'])->first()->toarray();

        if ($puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] == 0) {
            $cotizacion['validez'] = '0 días';
        } else {
            //            $horas = $puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] * 24;
            //            $cotizacion['validez'] = $horas.' horas';
            $cotizacion['validez'] = $puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] . ' días hábiles';
        }

        $cotizacion['expira'] = date('Y-m-d', strtotime($puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] . ' day', strtotime($cotizacion['updated_at'])));

        $cotizacion['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];

        $data = $cotizacion;
        //return view('cotizacionPDF', $data);
        $pdf = Pdf::loadView('cotizacionProspectoPDF', $data);
        return $pdf->stream('cotizacion-' . $cotizacion['cotizacion'] . '.pdf');
    }

    public function guardarordendecompra(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $cotizacion = GpCotizacion::where('id', $data['id'])->get()->first();
        $cotizacion->orden_de_compra = $data['orden_de_compra'];
        $cotizacion->nota_interna = $data['nota_interna'];
        $cotizacion->save();

        return response([
            'success' => true,
            'message' => 'Orden de compra guardada'
        ]);
    }

    public function guardarmensaje(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $cotizacion = GpCotizacion::where('id', $data['id'])->get()->first();
        $cotizacion->orden_de_compra = $data['orden_de_compra'];
        $cotizacion->aprobado_comercial_mensaje = $data['aprobado_comercial_mensaje'];
        $cotizacion->save();

        return response([
            'success' => true,
            'message' => 'Todo guardado'
        ]);
    }

    public function checkrucprospecto(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'CLI_RUC' => '',
        ]);

        $cliente = GpCliente::where('CLI_RUC', $data['CLI_RUC'])->first();
        if ($cliente) {
            $data['cliente_id'] = $cliente->id;
            $funciones = new FuncionesController();
            $cliente = $funciones->getcliente($data);

            return response([
                'success' => true,
                'cliente' => $cliente
            ]);
        }

        $prospecto = GpProspecto::where('CLI_RUC', $data['CLI_RUC'])->first();
        if ($prospecto) {
            return response([
                'success' => true,
                'prospecto' => $prospecto
            ]);
        }

        return response([
            'success' => false,
        ]);
    }


    public function getlistNonSaleReasons()
    {
        return $this->quotationService->getNonSalesReasons();
    }


    public function updateNonSaleAndReminderData(Request $request)
    {
        $data = $request->all();
        return $this->quotationService->updateNonSaleAndReminderData($data);
    }


    /**
     * Summary of sendAllQuotesRecorderSQLServer
     *  Se enviar todas las cotizaciones pendientes de recordatorio de venta a SQL Server que tengan un motivo asignado
     * @return void
     */
    public function sendAllQuotesRecorderSQLServer()
    {
        $cotizaciones = GpCotizacion::with(['usuario', 'usuariorecordatorio', 'motivosNoVenta'])->where('cotizacion_venta_motivo_id', '!=', null)->get();
        foreach ($cotizaciones as $cotizacion) {
            $this->quotationService->sendStoreReminderGp($cotizacion);
        }
    }


    /** lista de items alterantivos traidos del apigp -cotizaciones sql server */

    public function getItemsAlternatives(Request $request)
    {
        try {
            $item = data_get($request, 'item', '');
            $lista = $this->quotationService->getItemsAlternatives($item);
            return $this->successResponse('Datos Obtenidos Correctamente', $lista, 200);
        } catch (HttpException $th) {
            return $this->buildErrorResponse(
                $th->getMessage(),
                $th->getStatusCode()
            );
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    /**
     * API PARA LISTAR LAS NOVEDADES DE LAS COTIZACIONES
     *
     */
    public function consultQuotationsNews(Request $request): JsonResponse
    {
        try {
            $resumen = $this->quotationService->consultQuotationsNews($request->all());
            return $this->successResponse('Datos Obtenidos Correctamente', $resumen, 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     *  para obtener el stock del articulo en tiempo real por item y no por cotización
     */

    public function obtenerStockItemPorBodega(Request $request)
    {
        $request->validate([
            'articulo' => 'required',
            'bodega' => 'required',
        ]);
        $articulo = $request->get('articulo', null);
        $bodega = $request->get('bodega', null);
        $cotizacionId = $request->get('cotizacion_id', null);
        try {
            $response = $this->quotationService->getStockItemForWarehouse($articulo, $bodega, $cotizacionId);
            return $this->successResponse('Stock obtenido correctamente', $response, 200);
        } catch (\Throwable $e) {
            Log::error(
                'Error al obtener stock',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'data' => $articulo
                ]
            );
            return $this->buildErrorResponse('Error al obtener stock', 500);
        }
    }

    public function obteneMargenItemlL(Request $request)
    {
        $request->validate([
            'articulo' => 'required',
        ]);
        try {
            $datosL = $this->quotationService->getMarginItem($request->articulo);
            return $this->successResponse('Información obtenida correctamente', $datosL, 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    /** seccion de cliente  */
    public function obtenerListaSegmentos()
    {
        try {
            $resp = $this->quotationService->getAllSegments();
            return $this->successResponse('Segmentos obtenidos correctamente', $resp, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function obtenerListaSubSegmentos(Request $request)
    {
        try {
            $coAtrPadre = $request->get('segmento', null);
            $resp = $this->quotationService->getSubSegmentBySegment($coAtrPadre);
            return $this->successResponse('Segmentos obtenidos correctamente', $resp, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function validarSegmentoCliente(Request $request)
    {
        $ruc = $request->get('ruc', null);
        try {
            $hasSegment = $this->quotationService->checkClientWithSegmentExist($ruc);
            if ($hasSegment['success'] === true) {
                return $this->buildExistResponse(true, 'Segmento encontrado', 200);
            }
            return $this->buildExistResponse(false, 'Segmento no encontrado', 202);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function actualizarSegmentoCliente(Request $request)
    {
        $ruc = $request->get('ruc', null);
        $segmento = $request->get('segmento', null);
        $subsegmento = $request->get('subsegmento', null);
        try {
            $this->quotationService->updateClientWithSegment($ruc, $segmento, $subsegmento);
            return $this->buildSuccessResponse('Segmento del cliente actualizado correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al actualizar el segmento del cliente :' . $e->getMessage(), 500);
        }
    }
}
