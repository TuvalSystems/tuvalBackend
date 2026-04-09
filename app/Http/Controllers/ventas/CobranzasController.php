<?php

namespace App\Http\Controllers\ventas;

use App\Enums\Ventas\OrderStatus;
use App\Enums\Ventas\TaxRateEnum;
use App\Http\Controllers\bodegas\DespachosController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FuncionesController;
use App\Http\Controllers\WebserviceController;
use App\Models\GpCierreCaja;
use App\Models\GpCobrosAplicacionFactura;
use App\Models\GpCobrosAplicacionMonto;
use App\Models\GpCobrosTarjetaFactura;
use App\Models\GpCobrosTarjetaMonto;
use App\Models\GpCronLog;
use App\Models\GpPedido;
use App\Models\GpRelacionPuntoVentaCaja;
use App\Models\GpUsuario;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Cobranzas\GpCobro;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\Cobranzas\GpCobrosEfectivoMonto;
use App\Models\Cobranzas\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\GpCobrosCheques;
use App\Models\Cobranzas\GpCobrosChequesMonto;
use App\Models\Cobranzas\GpCobrosChequesFacturas;
use App\Models\GpCliente;
use App\Models\GpRelacionUsuarioCaja;
use App\Models\GpCajaBancos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Exports\EstadodecuentaExport;
use App\Notifications\User\UserNotification;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;
use App\Services\Venta\Contracts\OrderServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;



class CobranzasController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly CollectionServiceInterface $collectionService) {}
    public function documentosvencidoscliente(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'CLI_RUC' => 'required',
        ]);

        $webservices = new WebserviceController();
        $documentosVencidos = $webservices->documentosvencidoscliente($data['CLI_RUC']);
        return response([
            'success' => true,
            'facturas' => $documentosVencidos,
        ]);
    }

    public function estadodecuentacliente(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'CLI_RUC' => 'required',
            'id' => 'required',
        ]);

        $webservices = new WebserviceController();

        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $data['CLI_RUC'],
        );

        $estadodecuenta = $webservices->cobranzas('ConsultaDetalleEstadoCuenta', $parameters);
        $estadodecuenta = $estadodecuenta['ConsultaDetalleEstadoCuentaResult']['ESTADO_DE_CUENTA_DETALLADO'][0];
        if (!isset($estadodecuenta['LISTA_CABECERA_ESTADO_CUENTA']['CABECERA_ESTADO_CUENTA'][0]['LISTA_DETALLE_ESTADO_CUENTA']['DETALLE_ESTADO_CUENTA'])) {
            $facturas = array();
        } else {
            $facturas = $estadodecuenta['LISTA_CABECERA_ESTADO_CUENTA']['CABECERA_ESTADO_CUENTA'][0]['LISTA_DETALLE_ESTADO_CUENTA']['DETALLE_ESTADO_CUENTA'];
        }

        $facturas_retenciones = array();
        foreach ($facturas as $key => &$factura) {
            $factura['ECD_DET_SALDO'] = $factura['ECD_DET_SALDO_CLIENTE'];
            $total = number_format($factura['ECD_DET_MONTO_ORIGINAL'], 2, '.', '');
            $subtotal =  round(TaxRateEnum::IVA_12->calculateSubtotal($total), 4);
            $iva = number_format($total - $subtotal, 2, '.', '');
            $factura['ECD_DET_SUBTOTAL'] = $subtotal;
            $factura['ECD_DET_IVA'] = $iva;
            $factura['ECD_DET_FECHA_EMISION'] = substr($factura['ECD_DET_FECHA_EMISION']['date'], 0, 10);
            $factura['ECD_DET_FECHA_VENCIMIENTO'] = substr($factura['ECD_DET_FECHA_VENCIMIENTO']['date'], 0, 10);
            $factura['aplicar_todo_efectivo'] = false;
            $factura['aplicar_monto_efectivo'] = 0;
            $factura['observacion_efectivo'] = "";

            $anterior = GpCobrosRetenciones::where('ret_factura', $factura['ECD_DET_NUM_DOC'])->first();

            if ($factura['ECD_DET_SALDO_CLIENTE'] == 0) {
                unset($facturas[$key]);
            } elseif ($factura['ECD_DET_TIPO_DOC'] == 'Facturas' && !$anterior) {
                $facturas_retenciones[] = $factura;
            }

            $depositos_pendientes = GpCobrosDeposito::join('gp_cobros_depositos_facturas', 'gp_cobros_depositos_facturas.deposito_id', 'gp_cobros_depositos.id')
                ->where('gp_cobros_depositos_facturas.id_factura', $factura['ECD_DET_NUM_DOC'])
                ->where('gp_cobros_depositos.estado', 'iniciado')
                ->select('gp_cobros_depositos_facturas.monto')
                ->get();

            foreach ($depositos_pendientes as $pendiente) {
                $factura['ECD_DET_SALDO_CLIENTE'] -= $pendiente->monto;
                $factura['ECD_DET_SALDO_BASE_CLIENTE'] -= $pendiente->monto;
                $factura['ECD_DET_SALDO'] -= $pendiente->monto;
            }
        }

        //REVISAR INTERNAMENTE SI YA ESTÄ GUARDADO EL COBRO

        $facturas = array_values($facturas);

        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $data['CLI_RUC'],
        );

        $cobros_sin_aplicar = $webservices->cobranzas('ConsultaCobrosSinAplicar', $parameters);
        $cobros_sin_aplicar = $cobros_sin_aplicar['ConsultaCobrosSinAplicarResult']['CONSULTA_COBROS_NO_APLICADOS'];

        $bancos_tuval = GpCajaBancos::where('CB_TIPO', 'BANCO_TUVAL')->where('estado', 'activo')->get();
        $bancos_cliente = GpCajaBancos::where('CB_TIPO', 'BANCO_CLIENTE')->get();
        $bancos_tarjetas = GpCajaBancos::where('CB_TIPO', 'TARJETA')->get();

        $pedidos = GpPedido::leftJoin('gp_cobros', 'gp_cobros.pedido', 'gp_pedidos.pedido')
            ->where('gp_pedidos.cliente_id', $data['id'])
            ->where('gp_pedidos.estado', OrderStatus::ESPERA)
            ->whereNull('gp_cobros.pedido')
            ->select('gp_pedidos.*')
            ->get();

        foreach ($pedidos as $key => $pedido) {
            if ($pedido->requiere_aprobacion_comercial == 'yes' && $pedido->aprobado_comercial == 'no')
                unset($pedidos[$key]);

            if ($pedido->requiere_aprobacion_crediticia == 'yes' && $pedido->aprobado_crediticio == 'no')
                unset($pedidos[$key]);
        }

        return response([
            'success' => true,
            'facturas' => $facturas,
            'facturas_retenciones' => $facturas_retenciones,
            'bancos_tuval' => $bancos_tuval,
            'bancos_cliente' => $bancos_cliente,
            'bancos_tarjetas' => $bancos_tarjetas,
            'cobros_sin_aplicar' => $cobros_sin_aplicar,
            'pedidos_por_cobrar' => $pedidos,
        ]);
    }

    public function estadodecuentaclientetuval($ruc)
    {
        //        $data = $request->validate([
        //            'CLI_RUC' => 'required',
        //        ]);

        $webservices = new WebserviceController();
        $funciones = new FuncionesController();

        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $ruc,
        );

        $cliente = GpCliente::where('CLI_RUC', $ruc)->first()->toArray();
        $detalle = $webservices->cobranzas('ConsultaDetalleEstadoCuentaTuval', $parameters);
        $resumen = $webservices->cobranzas('ConsultaResumenEstadoCuentaTuval', $parameters);

        foreach ($detalle['ConsultaDetalleEstadoCuentaTuvalResult']['ESTADO_CUENTA_TUVAL'] as $key => $value) {
            if ($value['Condicion_Pago'] == '') {
                $fecha_inicio = substr($value['Fecha_Emision']['date'], 0, 10);
                $fecha_fin = substr($value['Fecha_Vencimiento']['date'], 0, 10);
                $detalle['ConsultaDetalleEstadoCuentaTuvalResult']['ESTADO_CUENTA_TUVAL'][$key]['Condicion_Pago'] = $funciones->dateDiffInDays($fecha_inicio, $fecha_fin) . ' DÍAS';
            }
        }

        $data = [
            'cliente' => $cliente,
            'detalle' => $detalle['ConsultaDetalleEstadoCuentaTuvalResult']['ESTADO_CUENTA_TUVAL'],
            'resumen' => $resumen['ConsultaResumenEstadoCuentaTuvalResult']['RESUMEN_ESTADO_CUENTA_TUVAL'][0],
        ];
        //        echo '<pre>';
        //        print_r($data);
        //        die();
        //return view('estadodecuentaclientePDF', $data);
        $pdf = PDF::loadView('estadodecuentaclientePDF', $data);
        return $pdf->stream('estadodecuentacliente-' . $cliente['CLI_RUC'] . '-' . date('Y-m-d') . '.pdf');
    }

    public function estadodecuentaclientetuvalexcel($ruc)
    {

        $webservices = new WebserviceController();
        $funciones = new FuncionesController();

        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $ruc,
        );

        $cliente = GpCliente::where('CLI_RUC', $ruc)->first()->toArray();
        $detalle = $webservices->cobranzas('ConsultaDetalleEstadoCuentaTuval', $parameters);
        $resumen = $webservices->cobranzas('ConsultaResumenEstadoCuentaTuval', $parameters);

        foreach ($detalle['ConsultaDetalleEstadoCuentaTuvalResult']['ESTADO_CUENTA_TUVAL'] as $key => $value) {
            if ($value['Condicion_Pago'] == '') {
                $fecha_inicio = substr($value['Fecha_Emision']['date'], 0, 10);
                $fecha_fin = substr($value['Fecha_Vencimiento']['date'], 0, 10);
                $detalle['ConsultaDetalleEstadoCuentaTuvalResult']['ESTADO_CUENTA_TUVAL'][$key]['Condicion_Pago'] = $funciones->dateDiffInDays($fecha_inicio, $fecha_fin) . ' DÍAS';
            }
        }

        $resumen = array(
            0 => [
                'tipo' => 'Tipo Doc',
                'numero' => 'Nro Doc',
                'fecha_doc' => 'Fecha doc',
                'fecha_venc' => 'Fecha Venc',
                'condicion_pago' => 'Cond Pago',
                'monto_original' => 'Valor Doc',
                'abono' => 'Abono',
                'saldo_pendiente' => 'Saldo Pend',
                'observacion' => '',
            ]
        );
        foreach ($detalle['ConsultaDetalleEstadoCuentaTuvalResult']['ESTADO_CUENTA_TUVAL'] as $key => $dat) {
            $resumen[$key + 1]['tipo'] = $dat['Tipo'];
            $resumen[$key + 1]['numero'] = $dat['Documento'];
            $resumen[$key + 1]['fecha_doc'] = substr($dat['Fecha_Emision']['date'], 0, 10);
            $resumen[$key + 1]['fecha_venc'] = substr($dat['Fecha_Vencimiento']['date'], 0, 10);
            $resumen[$key + 1]['condicion_pago'] = $dat['Condicion_Pago'];
            $resumen[$key + 1]['monto_original'] = number_format($dat['Monto_Original'], 2, '.', '');
            $resumen[$key + 1]['abono'] = number_format($dat['Abono'], 2, '.', '');
            $resumen[$key + 1]['saldo_pendiente'] = number_format($dat['Saldo_Pendiente'], 2, '.', '');
            $resumen[$key + 1]['observacion'] = $dat['Observacion'];
        }

        //        echo '<pre>';
        //        print_r($detalle['ConsultaDetalleEstadoCuentaTuvalResult']['ESTADO_CUENTA_TUVAL']);
        //        die();

        $export = new EstadodecuentaExport($resumen);

        return Excel::download($export, 'estado_de_cuenta_' . $ruc . '.xlsx'); //simon
    }

    public function registrarcobro(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = Auth::user();

        $caja = GpRelacionUsuarioCaja::join('gp_cajas', 'gp_cajas.CAJA_CODIGO', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->join('gp_relacion_punto_venta_cajas', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_CAJA', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->where('CAJ_USER_USUARIO', $user->USERID)
            ->where('gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_PUNTO_VENTA', $data['puntodeventa'])
            ->select('gp_relacion_usuario_cajas.*')
            ->first();

        $cobro = new GpCobro();

        $ultimoPedido = GpCobro::orderBy('id', 'DESC')->first();

        if (!$ultimoPedido) {
            $ultimoPedido = new \stdClass();
            $ultimoPedido->id = 0;
        }
        $cobro->numero_recibo = 'RC-' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);
        $cobro->user_id = $user->id;
        $cobro->cliente_id = $data['cliente_id'];
        $cobro->caja = $caja->CAJ_USER_CAJA;
        $cobro->fecha = date('Y-m-d H:i:s');
        $cobro->estado = 'iniciado';
        $cobro->referencia = $data['referencia'];
        if (!isset($data['pago_a_padido']))
            $data['pago_a_padido'] = null;
        $cobro->pedido = $data['pago_a_padido'];
        $cobro->save();

        if (isset($data['retenciones']) && count($data['retenciones']) > 0) {
            foreach ($data['retenciones'] as $key => $retencion) {

                $save_retencion = new GpCobrosRetenciones;
                $save_retencion->cobro_id = $cobro->id;
                $save_retencion->ret_factura = $retencion['ret_factura'];
                $save_retencion->ret_fecha_emision = $retencion['ret_fecha_emision'];
                $save_retencion->ret_fecha_vencimiento = $retencion['ret_fecha_vencimiento'];
                $save_retencion->ret_subtotal_factura = $retencion['ret_subtotal_factura'];
                $save_retencion->ret_iva_factura = $retencion['ret_iva_factura'];
                $save_retencion->ret_total_factura = $retencion['ret_original_factura'];
                $save_retencion->ret_saldo_factura = $retencion['ret_saldo_factura'];
                $save_retencion->ret_iva = $retencion['ret_iva'];
                $save_retencion->ret_porcentaje_iva = $retencion['ret_porcentaje_iva'];
                $save_retencion->ret_renta = $retencion['ret_renta'];
                $save_retencion->ret_porcentaje_renta = $retencion['ret_porcentaje_renta'];

                $save_retencion->ret_iva_srv = $retencion['ret_iva_srv'];
                $save_retencion->ret_porcentaje_iva_srv = $retencion['ret_porcentaje_iva_srv'];
                $save_retencion->ret_renta_srv = $retencion['ret_renta_srv'];
                $save_retencion->ret_porcentaje_renta_srv = $retencion['ret_porcentaje_renta_srv'];

                $save_retencion->ret_emision = $retencion['ret_emision'];
                $save_retencion->ret_caducidad = $retencion['ret_caducidad'];
                $save_retencion->ret_comprobante = $retencion['ret_comprobante'];
                $save_retencion->ret_autorizacion = $retencion['ret_autorizacion'];
                $save_retencion->total = $retencion['total'];
                $save_retencion->save();
            }
        }
        if (isset($data['ingreso_efectivo']['monto_original']) && $data['ingreso_efectivo']['monto_original'] > 0) {
            $save_efectivo_monto = new GpCobrosEfectivoMonto;
            $save_efectivo_monto->cobro_id = $cobro->id;
            $save_efectivo_monto->monto = $data['ingreso_efectivo']['monto'];
            $save_efectivo_monto->save();

            foreach ($data['ingreso_efectivo']['facturas'] as $key => $factura) {
                $save_efectivo = new GpCobrosEfectivoFactura;
                $save_efectivo->cobro_id = $cobro->id;
                $save_efectivo->id_factura = $factura['ECD_DET_NUM_DOC'];
                $save_efectivo->monto = $factura['aplicar_monto_efectivo'];
                $save_efectivo->observacion = $factura['observacion_efectivo'];
                $save_efectivo->save();
            }
        }

        if (isset($data['ingreso_depositos']) && count($data['ingreso_depositos']) > 0) {
            foreach ($data['ingreso_depositos'] as $deposito) {
                $save_deposito = new GpCobrosDeposito;
                $save_deposito->cobro_id = $cobro->id;
                $save_deposito->estado = 'iniciado';
                $save_deposito->tipo = $deposito['tipo'];
                $save_deposito->banco = $deposito['banco'];
                $save_deposito->comprobante = $deposito['dep_comprobante'];
                $save_deposito->fecha = $deposito['dep_fecha'];
                $save_deposito->monto = $deposito['monto'];
                $save_deposito->save();

                foreach ($deposito['facturas'] as $factura) {
                    if (!isset($factura['observacion_deposito']))
                        $factura['observacion_deposito'] = null;
                    $save_factura = new GpCobrosDepositosFacturas;
                    $save_factura->cobro_id = $cobro->id;
                    $save_factura->deposito_id = $save_deposito->id;
                    $save_factura->id_factura = $factura['ECD_DET_NUM_DOC'];
                    $save_factura->monto = $factura['aplicar_monto_deposito'];
                    $save_factura->observacion = $factura['observacion_deposito'];
                    $save_factura->save();
                }
            }
        }

        if (isset($data['ingreso_cheques']['monto_original']) && $data['ingreso_cheques']['monto_original'] > 0) {
            $save_cheques_monto = new GpCobrosChequesMonto();
            $save_cheques_monto->cobro_id = $cobro->id;
            $save_cheques_monto->monto = $data['ingreso_cheques']['monto_original'];
            $save_cheques_monto->saldo = $data['ingreso_cheques']['monto_no_aplicado'];
            $save_cheques_monto->save();

            foreach ($data['ingreso_cheques']['cheques'] as $key => $cheque) {
                $save_cheque = new GpCobrosCheques();
                $save_cheque->cobro_id = $cobro->id;
                $save_cheque->banco = $cheque['banco_id'];
                $save_cheque->cuenta = $cheque['cuenta'];
                $save_cheque->cheque = $cheque['cheque'];
                $save_cheque->monto = $cheque['monto'];
                $save_cheque->fecha_emision = $cheque['fecha_emision'];
                $save_cheque->fecha_vencimiento = $cheque['fecha_vencimiento'];
                $save_cheque->comentario = $cheque['comentario'];
                $save_cheque->save();
            }

            foreach ($data['ingreso_cheques']['facturas'] as $key => $factura) {

                if (!isset($factura['observacion_efectivo']))
                    $factura['observacion_efectivo'] = null;

                $save_factura = new GpCobrosChequesFacturas();
                $save_factura->cobro_id = $cobro->id;
                $save_factura->id_factura = $factura['ECD_DET_NUM_DOC'];
                $save_factura->monto = $factura['aplicar_monto_cheque'];
                $save_factura->observacion = $factura['observacion_efectivo'];
                $save_factura->ECD_DET_FECHA_VENCIMIENTO = $factura['ECD_DET_FECHA_VENCIMIENTO'];
                $save_factura->save();
            }
        }

        if (isset($data['ingreso_tarjeta']['monto_original']) && $data['ingreso_tarjeta']['monto_original'] > 0) {
            $save_tarjeta_monto = new GpCobrosTarjetaMonto();
            $save_tarjeta_monto->cobro_id = $cobro->id;
            $save_tarjeta_monto->monto = $data['ingreso_tarjeta']['monto'];
            $save_tarjeta_monto->fecha = $data['ingreso_tarjeta']['fecha'];
            $save_tarjeta_monto->banco = $data['ingreso_tarjeta']['banco_id'];
            $save_tarjeta_monto->comprobante = $data['ingreso_tarjeta']['comprobante'];
            $save_tarjeta_monto->comentario = $data['ingreso_tarjeta']['comentario'];
            $save_tarjeta_monto->save();

            foreach ($data['ingreso_tarjeta']['facturas'] as $key => $factura) {

                if (!isset($factura['observacion_tarjeta']))
                    $factura['observacion_tarjeta'] = null;

                $save_tarjeta = new GpCobrosTarjetaFactura();
                $save_tarjeta->cobro_id = $cobro->id;
                $save_tarjeta->id_factura = $factura['ECD_DET_NUM_DOC'];
                $save_tarjeta->monto = $factura['aplicar_monto_tarjeta'];
                $save_tarjeta->observacion = $factura['observacion_tarjeta'];
                $save_tarjeta->save();
            }
        }

        if (isset($data['ingreso_aplicaciones']) && count($data['ingreso_aplicaciones']) > 0) {
            foreach ($data['ingreso_aplicaciones'] as $aplicacion) {
                $save_aplicacion = new GpCobrosAplicacionMonto();
                $save_aplicacion->cobro_id = $cobro->id;
                $save_aplicacion->monto = $aplicacion['monto'];
                $save_aplicacion->cobro = $aplicacion['detalle']['CN_DOCUMENTO'];
                $save_aplicacion->tipo = $aplicacion['detalle']['CN_TIPO'];
                $save_aplicacion->comentario = $aplicacion['comentario'];
                $save_aplicacion->save();

                foreach ($aplicacion['facturas'] as $factura) {
                    if (!isset($factura['observacion_aplicacion']))
                        $factura['observacion_aplicacion'] = null;
                    $save_factura = new GpCobrosAplicacionFactura();
                    $save_factura->cobro_id = $cobro->id;
                    $save_factura->aplicacion_id = $save_aplicacion->id;
                    $save_factura->id_factura = $factura['ECD_DET_NUM_DOC'];
                    $save_factura->monto = $factura['aplicar_monto_aplicacion'];
                    $save_factura->observacion = $factura['observacion_aplicacion'];
                    $save_factura->save();
                }
            }
        }

        $this->enviarcobro($cobro->id);

        return response([
            'success' => true,
            'message' => 'Cobro ' . $cobro->numero_recibo . ' correctamente guardado'
        ]);
    }

    public function cobrosregistrados(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required',
        ]);

        $cobros = GpCobro::where('cliente_id', $data['id'])
            ->orderBy('fecha', 'DESC')
            ->get();

        return response([
            'success' => true,
            'cobros_registrados' => $cobros
        ]);
    }

    public function depositosporaprobar(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $depositos = GpCobrosDeposito::where('monto', '>', 0)
            ->join('gp_cobros', 'gp_cobros.id', '=', 'gp_cobros_depositos.cobro_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', '=', 'gp_cobros_depositos.banco')
            ->join('gp_clientes', 'gp_clientes.id', '=', 'gp_cobros.cliente_id')
            ->where('gp_cobros_depositos.estado', 'iniciado')
            ->orderBy('gp_cobros_depositos.fecha', 'DESC')
            ->get(['gp_cobros_depositos.*', 'gp_cobros.numero_recibo', 'gp_caja_bancos.CB_DESCRIPCION', 'gp_clientes.CLI_RUC', 'gp_clientes.CLI_RAZON_SOCIAL']);

        return response([
            'success' => true,
            'depositos' => $depositos
        ]);
    }

    public function aprobardeposito(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $request->validate([
            'id' => 'required',
        ]);
        $gpUser = GpUsuario::find(auth()->user()->id);
        $deposito = GpCobrosDeposito::where('id', $request->id)->first();
        $deposito->fecha_aprobacion = Carbon::now()->format('Y-m-d H:i:s');
        $deposito->user_aprobador_id = $gpUser->id;
        $deposito->estado = 'revisado';
        $deposito->save();

        $this->enviarcobro($deposito->cobro_id);

        $cobro = GpCobro::where('id', $deposito->cobro_id)->first();
        $cobro->fecha_aprobacion_rechazo = Carbon::now()->format('Y-m-d H:i:s');
        $cobro->user_aprobador_id = $gpUser->id;
        $cobro->save();
        $dato = GpCobrosDeposito::where('gp_cobros_depositos.id', $request->id)
            ->join('gp_cobros', 'gp_cobros.id', '=', 'gp_cobros_depositos.cobro_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', '=', 'gp_cobros_depositos.banco')
            ->join('gp_clientes', 'gp_clientes.id', '=', 'gp_cobros.cliente_id')
            ->orderBy('gp_cobros_depositos.fecha', 'DESC')
            ->get([
                'gp_cobros_depositos.*',
                'gp_cobros.numero_recibo',
                'gp_cobros.user_id',
                'gp_caja_bancos.CB_DESCRIPCION',
                'gp_clientes.CLI_RUC',
                'gp_clientes.CLI_RAZON_SOCIAL'
            ])->first();


        $mensaje = 'Depósito ' . $dato->comprobante . ' a la cuenta ' . $dato->CB_DESCRIPCION . ' del cliente ' . $dato->CLI_RAZON_SOCIAL . ' con RUC: ' . $dato->CLI_RUC . ' ha sido aprobado';
        $gpUser = GpUsuario::where('id', $dato->user_id)->first();
        if ($gpUser) {
            $gpUser->notify(new UserNotification('Depósito aprobado', $mensaje));
        }

        return response([
            'success' => true,
            'message' => 'Depósito enviado'
        ]);
    }

    public function rechazardeposito(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $deposito = GpCobrosDeposito::where('id', $data['id'])->first();


        $dato = GpCobrosDeposito::where('gp_cobros_depositos.id', $request->id)
            ->join('gp_cobros', 'gp_cobros.id', '=', 'gp_cobros_depositos.cobro_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', '=', 'gp_cobros_depositos.banco')
            ->join('gp_clientes', 'gp_clientes.id', '=', 'gp_cobros.cliente_id')
            ->orderBy('gp_cobros_depositos.fecha', 'DESC')
            ->get([
                'gp_cobros_depositos.*',
                'gp_cobros.numero_recibo',
                'gp_cobros.user_id',
                'gp_caja_bancos.CB_DESCRIPCION',
                'gp_clientes.CLI_RUC',
                'gp_clientes.CLI_RAZON_SOCIAL'
            ])->first();


        $mensaje = 'Depósito/Transferencia ' . $dato->comprobante . ' a la cuenta ' . $dato->CB_DESCRIPCION . ' del cliente ' . $dato->CLI_RAZON_SOCIAL . ' con RUC: ' . $dato->CLI_RUC . ' ha sido <b>RECHAZADO</b>';
        if ($data['motivo'] != '') {
            $mensaje .= '<br> Motivo: ' . $data['motivo'];
        }

        $gpUser = GpUsuario::where('id', $dato->user_id)->first();
        $cobro = GpCobro::where('id', $deposito->cobro_id)->first();
        $cobro->fecha_aprobacion_rechazo = Carbon::now()->format('Y-m-d H:i:s');
        $cobro->user_aprobador_id = $gpUser->id;
        $cobro->save();

        if ($gpUser) {
            $gpUser->notify(new UserNotification('Depósito/Transferencia rechazado', $mensaje));
        }

        $deposito->delete();

        $this->enviarcobro($dato->cobro_id);

        return response([
            'success' => true,
            'message' => 'Depósito rechazado'
        ]);
    }

    public function enviarcobros()
    {
        $cobros = GpCobro::whereNotIn('estado', ['enviado', 'no_enviado'])->get();

        foreach ($cobros as $cobro) {
            $this->enviarcobro($cobro->id);
        }

        $this->checkcobrosenviados();

        $cronLog = new GpCronLog();
        $cronLog->metodo = 'enviarcobros';
        $cronLog->fecha = date('Y-m-d H:i:s');
        $cronLog->save();
    }

    public function enviarcobro($id): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $webservice = new WebserviceController;
        $cobro = GpCobro::where('id', $id)->first();
        $cliente = GpCliente::where('id', $cobro->cliente_id)->first();
        $alerta = '';
        $estadoDeCuenta = '';
        $efectivo = GpCobrosEfectivoMonto::where('cobro_id', $cobro->id)
            ->where('monto', '>', 0)
            ->where(function ($q) {
                $q->where('mensaje_ws', '=', null)
                    ->orWhere('mensaje_ws', '!=', 'Registro de cobro guardado correctamente.')
                    ->where('mensaje_ws', '!=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
            })
            ->first();

        if ($efectivo) {
            $efectivo_facturas = GpCobrosEfectivoFactura::where('cobro_id', $cobro->id)->get();
            $envio_facturas_efe = array();
            $monto_deposito = 0;
            foreach ($efectivo_facturas as $fac) {
                $envio_facturas_efe[] = array(
                    'Numero_Recibo' => $cobro->numero_recibo . '-EFE',
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Numero_Factura' => $fac->id_factura,
                    'Monto_Aplicado' => $fac->monto,
                    'Observacion' => $fac->observacion,
                );
                $monto_deposito += $fac->monto;
            }
            $monto_deposito = number_format($monto_deposito, 2, '.', '');
            $Cabecera_Cobro_Regular = array(
                array(
                    'Numero_Recibo' => $cobro->numero_recibo . '-EFE',
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Fecha_Cobro' => $cobro->fecha, //str_replace(' ', 'T',$cobro->fecha),//.'.8116407+02:00',
                    'Tipo_Cobro' => 'EF',
                    'Observacion' => $cobro->referencia,
                    'Solicitud_Descuento' => $cobro->pedido,
                    'Id_vendedor' => $cobro->caja,
                    'Monto_Cobro' => $efectivo->monto,
                    'Saldo_Pendiente' => number_format($efectivo->monto - $monto_deposito, 2, '.', ''),
                    'Banco_Tuval' => $cobro->caja,
                    'Fecha_Deposito_Trf' => $cobro->fecha, //$efectivo->fecha.'T00:00:00',
                    'Numero_Papeleta' => '', //$efectivo->comprobante
                )
            );

            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Cabecera' => $Cabecera_Cobro_Regular,
                'Facturas' => $envio_facturas_efe
            );

            $estadoDeCuenta = $webservice->cobranzas('CreaCobroRegular', $parameters);

            if (isset($estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'])) {

                $efectivo->codigo_ws = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Codigo'];
                $efectivo->mensaje_ws = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'];
                $efectivo->save();
                $mensaje = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'];
                if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $alerta .= '<p>Efectivo de ' . $cobro->numero_recibo . ': ' . $mensaje . '.</p>';
                }
            }
        }

        $retenciones = GpCobrosRetenciones::where('cobro_id', $cobro->id)->get();
        foreach ($retenciones as $retencion) {
            if ($retencion->ret_iva > 0 && $retencion->mensaje_ws_iva != 'Registro de cobro guardado correctamente.' && $retencion->mensaje_ws_iva != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                $Cabecera_Cobro_Retencion_iva = array(
                    array(
                        'Numero_Recibo' => $cobro->numero_recibo . 'I' . $retencion->id,
                        'Id_Cliente' => $cliente->CLI_RUC,
                        'Fecha_Cobro' => $retencion->ret_emision,
                        'Fecha_Caducidad' => $retencion->ret_caducidad,
                        'Tipo_Cobro' => 'RV',
                        'Observacion' => $cobro->referencia,
                        'Id_vendedor' => $cobro->caja,
                        'Monto_Cobro' => $retencion->ret_iva,
                        'Numero_Retencion' => $retencion->ret_comprobante,
                        'Numero_Autorizacion' => $retencion->ret_autorizacion,
                        'Porcentaje_Retencion' => $retencion->ret_porcentaje_iva
                    )
                );
                $Facturas_Cobros_iva = array(
                    array(
                        'Numero_Recibo' => $cobro->numero_recibo . 'I' . $retencion->id,
                        'Id_Cliente' => $cliente->CLI_RUC,
                        'Numero_Factura' => $retencion->ret_factura,
                        'Monto_Aplicado' => $retencion->ret_iva,
                        'Observacion' => '',
                    )
                );

                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Cabecera' => $Cabecera_Cobro_Retencion_iva,
                    'Facturas' => $Facturas_Cobros_iva
                );

                $estadoDeCuenta = $webservice->cobranzas('CreaCobroRetencion', $parameters);
                if (isset($estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'])) {

                    $retencion->codigo_ws_iva = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Codigo'];
                    $retencion->mensaje_ws_iva = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'];
                    $retencion->save();

                    $mensaje = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'];
                    if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                        $alerta .= '<p>Retención IVA factura ' . $retencion->ret_factura . ': ' . $mensaje . '.</p>';
                    }
                }
            }
            if ($retencion->ret_renta > 0 && $retencion->mensaje_ws_renta != 'Registro de cobro guardado correctamente.' && $retencion->mensaje_ws_renta != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                $Cabecera_Cobro_Retencion_renta = array(
                    array(
                        'Numero_Recibo' => $cobro->numero_recibo . 'R' . $retencion->id,
                        'Id_Cliente' => $cliente->CLI_RUC,
                        'Fecha_Cobro' => $retencion->ret_emision,
                        'Fecha_Caducidad' => $retencion->ret_caducidad,
                        'Tipo_Cobro' => 'RF',
                        'Observacion' => $cobro->referencia,
                        'Id_vendedor' => $cobro->caja,
                        'Monto_Cobro' => $retencion->ret_renta,
                        'Numero_Retencion' => $retencion->ret_comprobante,
                        'Numero_Autorizacion' => $retencion->ret_autorizacion,
                        'Porcentaje_Retencion' => $retencion->ret_porcentaje_renta
                    )
                );
                $Facturas_Cobros_renta = array(
                    array(
                        'Numero_Recibo' => $cobro->numero_recibo . 'R' . $retencion->id,
                        'Id_Cliente' => $cliente->CLI_RUC,
                        'Numero_Factura' => $retencion->ret_factura,
                        'Monto_Aplicado' => $retencion->ret_renta,
                        'Observacion' => '',
                    )
                );
                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Cabecera' => $Cabecera_Cobro_Retencion_renta,
                    'Facturas' => $Facturas_Cobros_renta
                );

                $estadoDeCuenta = $webservice->cobranzas('CreaCobroRetencion', $parameters);
                if (isset($estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'])) {
                    $retencion->codigo_ws_renta = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Codigo'];
                    $retencion->mensaje_ws_renta = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'];

                    $retencion->save();
                    $mensaje = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'];
                    if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                        $alerta .= '<p>Retención RENTA factura ' . $retencion->ret_factura . ': ' . $mensaje . '.</p>';
                    }
                }
            }

            if ($retencion->ret_iva_srv > 0 && $retencion->mensaje_ws_iva_srv != 'Registro de cobro guardado correctamente.' && $retencion->mensaje_ws_iva_srv != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                $Cabecera_Cobro_Retencion_iva_srv = array(
                    array(
                        'Numero_Recibo' => $cobro->numero_recibo . 'ISRV' . $retencion->id,
                        'Id_Cliente' => $cliente->CLI_RUC,
                        'Fecha_Cobro' => $retencion->ret_emision,
                        'Fecha_Caducidad' => $retencion->ret_caducidad,
                        'Tipo_Cobro' => 'RV',
                        'Observacion' => $cobro->referencia,
                        'Id_vendedor' => $cobro->caja,
                        'Monto_Cobro' => $retencion->ret_iva_srv,
                        'Numero_Retencion' => $retencion->ret_comprobante,
                        'Numero_Autorizacion' => $retencion->ret_autorizacion,
                        'Porcentaje_Retencion' => $retencion->ret_porcentaje_iva_srv
                    )
                );
                $Facturas_Cobros_iva_srv = array(
                    array(
                        'Numero_Recibo' => $cobro->numero_recibo . 'ISRV' . $retencion->id,
                        'Id_Cliente' => $cliente->CLI_RUC,
                        'Numero_Factura' => $retencion->ret_factura,
                        'Monto_Aplicado' => $retencion->ret_iva_srv,
                        'Observacion' => '',
                    )
                );

                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Cabecera' => $Cabecera_Cobro_Retencion_iva_srv,
                    'Facturas' => $Facturas_Cobros_iva_srv
                );

                $estadoDeCuenta = $webservice->cobranzas('CreaCobroRetencion', $parameters);
                if (isset($estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'])) {

                    $retencion->codigo_ws_iva_srv = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Codigo'];
                    $retencion->mensaje_ws_iva_srv = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'];
                    $retencion->save();

                    $mensaje = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'];
                    if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                        $alerta .= '<p>Retención IVA SRV factura ' . $retencion->ret_factura . ': ' . $mensaje . '.</p>';
                    }
                }
            }
            if ($retencion->ret_renta_srv > 0 && $retencion->mensaje_ws_renta_srv != 'Registro de cobro guardado correctamente.' && $retencion->mensaje_ws_renta_srv != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                $Cabecera_Cobro_Retencion_renta_srv = array(
                    array(
                        'Numero_Recibo' => $cobro->numero_recibo . 'RSRV' . $retencion->id,
                        'Id_Cliente' => $cliente->CLI_RUC,
                        'Fecha_Cobro' => $retencion->ret_emision,
                        'Fecha_Caducidad' => $retencion->ret_caducidad,
                        'Tipo_Cobro' => 'RF',
                        'Observacion' => $cobro->referencia,
                        'Id_vendedor' => $cobro->caja,
                        'Monto_Cobro' => $retencion->ret_renta_srv,
                        'Numero_Retencion' => $retencion->ret_comprobante,
                        'Numero_Autorizacion' => $retencion->ret_autorizacion,
                        'Porcentaje_Retencion' => $retencion->ret_porcentaje_renta_srv
                    )
                );
                $Facturas_Cobros_renta_srv = array(
                    array(
                        'Numero_Recibo' => $cobro->numero_recibo . 'RSRV' . $retencion->id,
                        'Id_Cliente' => $cliente->CLI_RUC,
                        'Numero_Factura' => $retencion->ret_factura,
                        'Monto_Aplicado' => $retencion->ret_renta_srv,
                        'Observacion' => '',
                    )
                );
                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Cabecera' => $Cabecera_Cobro_Retencion_renta_srv,
                    'Facturas' => $Facturas_Cobros_renta_srv
                );

                $estadoDeCuenta = $webservice->cobranzas('CreaCobroRetencion', $parameters);
                if (isset($estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'])) {
                    $retencion->codigo_ws_renta_srv = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Codigo'];
                    $retencion->mensaje_ws_renta_srv = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'];

                    $retencion->save();
                    $mensaje = $estadoDeCuenta['CreaCobroRetencionResult']['Retorno'][0]['Mensaje'];
                    if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                        $alerta .= '<p>Retención RENTA SRV factura ' . $retencion->ret_factura . ': ' . $mensaje . '.</p>';
                    }
                }
            }
        }

        $depositos = GpCobrosDeposito::where('cobro_id', $cobro->id)
            ->where('monto', '>', 0)
            ->where('estado', 'revisado')
            ->where(function ($q) {
                $q->where('mensaje_ws', '=', null)
                    ->orWhere('mensaje_ws', '!=', 'Registro de cobro guardado correctamente.')
                    ->where('mensaje_ws', '!=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
            })
            ->get();

        foreach ($depositos as $key => $dep) {

            $depositos_facturas = GpCobrosDepositosFacturas::where('cobro_id', $cobro->id)->where('deposito_id', $dep->id)->get();
            $envio_facturas_dep = array();
            $monto_deposito = 0;
            foreach ($depositos_facturas as $fac) {
                $envio_facturas_dep[] = array(
                    'Numero_Recibo' => $cobro->numero_recibo . 'D' . $dep->id,
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Numero_Factura' => $fac->id_factura,
                    'Monto_Aplicado' => $fac->monto,
                    'Observacion' => $fac->observacion,
                );
                $monto_deposito += $fac->monto;
            }
            $monto_deposito = number_format($monto_deposito, 2, '.', '');
            $Cabecera_Cobro_Regular = array(
                array(
                    'Numero_Recibo' => $cobro->numero_recibo . 'D' . $dep->id,
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Fecha_Cobro' => $cobro->fecha,
                    'Tipo_Cobro' => $dep->tipo,
                    'Observacion' => $cobro->referencia,
                    'Solicitud_Descuento' => $cobro->pedido,
                    'Id_vendedor' => $cobro->caja,
                    'Monto_Cobro' => $dep->monto,
                    'Saldo_Pendiente' => number_format($dep->monto - $monto_deposito, 2, '.', ''),
                    'Banco_Tuval' => $dep->banco,
                    'Fecha_Deposito_Trf' => $dep->fecha . ' 00:00:00',
                    'Numero_Papeleta' => $dep->comprobante
                )
            );

            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Cabecera' => $Cabecera_Cobro_Regular,
                'Facturas' => $envio_facturas_dep
            );

            $estadoDeCuenta = $webservice->cobranzas('CreaCobroRegular', $parameters);

            if (isset($estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'])) {

                $dep->codigo_ws = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Codigo'];
                $dep->mensaje_ws = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'];
                $mensaje = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'];
                if ($mensaje == 'Registro de cobro guardado correctamente.' || $mensaje == 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $dep->estado = 'enviado';
                }
                $dep->save();

                if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $alerta .= '<p>Depositos de ' . $cobro->numero_recibo . ': ' . $mensaje . '.</p>';
                }
            }
        }

        $depositos_alerta = GpCobrosDeposito::where('cobro_id', $cobro->id)
            ->where('monto', '>', 0)
            ->where('estado', 'iniciado')
            ->get()->first();

        if ($depositos_alerta) {
            $alerta .= '<p>Existen depósitos pendientes de aprobación</p>';
        }

        $cheques = GpCobrosChequesMonto::where('cobro_id', $cobro->id)

            ->where(function ($q) {
                $q->where('mensaje_ws', '=', null)
                    ->orWhere('mensaje_ws', '!=', 'Registro de cobro guardado correctamente.')
                    ->where('mensaje_ws', '!=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
            })
            ->first();

        if ($cheques) {
            $cheques_facturas = GpCobrosChequesFacturas::where('cobro_id', $cobro->id)->get();
            $envio_che_facturas = array();
            $monto_aplicado = 0;
            foreach ($cheques_facturas as $fac) {
                $envio_che_facturas[] = array(
                    'Numero_Recibo' => $cobro->numero_recibo . '-CHE',
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Numero_Factura' => $fac->id_factura,
                    'Monto_Aplicado' => $fac->monto,
                    'Observacion' => $fac->observacion,
                );
                $monto_aplicado += $fac->monto;
            }
            $monto_aplicado = number_format($monto_aplicado, 2, '.', '');

            $cobrosCheques = GpCobrosCheques::where('cobro_id', $cobro->id)->get();
            $envio_che_cheques = array();
            $monto_cheques = 0;
            foreach ($cobrosCheques as $che) {
                $envio_che_cheques[] = array(
                    'Numero_Recibo' => $cobro->numero_recibo . '-CHE',
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Banco_Cliente' => $che->banco,
                    'Numero_Cuenta' => $che->cuenta,
                    'Fecha_Vencimiento' => $che->fecha_vencimiento,
                    'Numero_Cheque' => $che->cheque,
                    'Monto_Cheque' => $che->monto,
                    'Origen_Cuenta' => 'P',
                    'Observacion' => $che->comentario,
                );
                $monto_cheques += $che->monto;
            }

            $Cabecera_Cobro_Cheques = array(
                array(
                    'Numero_Recibo' => $cobro->numero_recibo . '-CHE',
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Fecha_Cobro' => $cobro->fecha, //str_replace(' ', 'T',$cobro->fecha),//.'.8116407+02:00',
                    'Observacion' => $cobro->referencia,
                    'Solicitud_Descuento' => $cobro->pedido,
                    'Id_vendedor' => $cobro->caja,
                    'Monto_Cobro' => $cheques->monto,
                    'Saldo_Pendiente' => number_format($monto_cheques - $monto_aplicado, 2, '.', ''),
                )
            );
            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Cabecera' => $Cabecera_Cobro_Cheques,
                'Cheques' => $envio_che_cheques,
                'Facturas' => $envio_che_facturas
            );
            //            echo '<pre>';
            //            print_r($parameters);
            //            die();
            $estadoDeCuenta = $webservice->cobranzas('CreaCobroCheques', $parameters);

            if (isset($estadoDeCuenta['CreaCobroChequesResult']['Retorno'][0]['Mensaje'])) {

                $cheques->codigo_ws = $estadoDeCuenta['CreaCobroChequesResult']['Retorno'][0]['Codigo'];
                $cheques->mensaje_ws = $estadoDeCuenta['CreaCobroChequesResult']['Retorno'][0]['Mensaje'];
                $cheques->save();
                $mensaje = $estadoDeCuenta['CreaCobroChequesResult']['Retorno'][0]['Mensaje'];
                if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $alerta .= '<p>Cheques de ' . $cobro->numero_recibo . ': ' . $mensaje . '.</p>';
                }
            }
        }

        $tarjeta = GpCobrosTarjetaMonto::where('cobro_id', $cobro->id)
            ->where('monto', '>', 0)
            ->where(function ($q) {
                $q->where('mensaje_ws', '=', null)
                    ->orWhere('mensaje_ws', '!=', 'Registro de cobro guardado correctamente.')
                    ->where('mensaje_ws', '!=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
            })
            ->first();

        if ($tarjeta) {
            $tarjeta_facturas = GpCobrosTarjetaFactura::where('cobro_id', $cobro->id)->get();
            $envio_facturas_tar = array();
            $monto_tarjeta = 0;
            foreach ($tarjeta_facturas as $fac) {
                $envio_facturas_tar[] = array(
                    'Numero_Recibo' => $cobro->numero_recibo . '-TC',
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Numero_Factura' => $fac->id_factura,
                    'Monto_Aplicado' => $fac->monto,
                    'Observacion' => $fac->observacion,
                );
                $monto_tarjeta += $fac->monto;
            }
            $monto_tarjeta = number_format($monto_tarjeta, 2, '.', '');
            $Cabecera_Cobro_Regular = array(
                array(
                    'Numero_Recibo' => $cobro->numero_recibo . '-TC',
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Fecha_Cobro' => $cobro->fecha, //str_replace(' ', 'T',$cobro->fecha),//.'.8116407+02:00',
                    'Tipo_Cobro' => 'TC',
                    'Observacion' => $tarjeta->comentario,
                    'Solicitud_Descuento' => $cobro->pedido,
                    'Id_vendedor' => $cobro->caja,
                    'Monto_Cobro' => $tarjeta->monto,
                    'Saldo_Pendiente' => number_format($tarjeta->monto - $monto_tarjeta, 2, '.', ''),
                    'Banco_Tuval' => $tarjeta->banco,
                    'Fecha_Deposito_Trf' => $cobro->fecha, //$efectivo->fecha.'T00:00:00',
                    'Numero_Papeleta' => $tarjeta->comprobante, //$efectivo->comprobante
                )
            );

            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Cabecera' => $Cabecera_Cobro_Regular,
                'Facturas' => $envio_facturas_tar
            );

            $estadoDeCuenta = $webservice->cobranzas('CreaCobroRegular', $parameters);

            if (isset($estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'])) {

                $tarjeta->codigo_ws = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Codigo'];
                $tarjeta->mensaje_ws = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'];
                $tarjeta->save();
                $mensaje = $estadoDeCuenta['CreaCobroRegularResult']['Retorno'][0]['Mensaje'];
                if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $alerta .= '<p>Efectivo de ' . $cobro->numero_recibo . ': ' . $mensaje . '.</p>';
                }
            }
        }

        $aplicaciones = GpCobrosAplicacionMonto::where('cobro_id', $cobro->id)
            ->where('monto', '>', 0)
            ->where(function ($q) {
                $q->where('mensaje_ws', '=', null)
                    ->orWhere('mensaje_ws', '!=', 'Registro de cobro guardado correctamente.')
                    ->where('mensaje_ws', '!=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
            })
            ->get();

        foreach ($aplicaciones as $key => $apl) {

            $aplicaciones_facturas = GpCobrosAplicacionFactura::where('cobro_id', $cobro->id)->where('aplicacion_id', $apl->id)->get();
            $envio_facturas_apl = array();

            foreach ($aplicaciones_facturas as $fac) {
                $envio_facturas_apl[] = array(
                    'Numero_Recibo' => $apl->cobro,
                    'Id_Cliente' => $cliente->CLI_RUC,
                    'Numero_Factura' => $fac->id_factura,
                    'Monto_Aplicado' => $fac->monto,
                    'Observacion' => $fac->observacion,
                );
            }

            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Ruc' => $cliente->CLI_RUC,
                'Cobro' => $apl->cobro,
                'Facturas' => $envio_facturas_apl
            );
            //            echo '<pre>';
            //            print_r($parameters);
            //            die();
            $estadoDeCuenta = $webservice->cobranzas('AplicaCobroAnticipo', $parameters);

            if (isset($estadoDeCuenta['AplicaCobroAnticipoResult']['Retorno'][0]['Mensaje'])) {

                $apl->codigo_ws = $estadoDeCuenta['AplicaCobroAnticipoResult']['Retorno'][0]['Codigo'];
                $apl->mensaje_ws = $estadoDeCuenta['AplicaCobroAnticipoResult']['Retorno'][0]['Mensaje'];
                $apl->save();
                $mensaje = $estadoDeCuenta['AplicaCobroAnticipoResult']['Retorno'][0]['Mensaje'];

                if ($mensaje != 'Registro de cobro guardado correctamente.' && $mensaje != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $alerta .= '<p>APlicaciones de saldos de ' . $cobro->numero_recibo . ': ' . $mensaje . '.</p>';
                }
            }
        }

        $cobro->alerta = ($alerta);
        $cobro->save();

        $this->checkcobrosenviados();

        $order = app(OrderServiceInterface::class);
        $dispatch = app(DispatchServiceInterface::class);
        $logistics = app(LogisticsOperationsServiceInterface::class);
        $despachos = new DespachosController($order, $dispatch, $logistics);
        $despachos->checkfacturasdespachos($cobro->id);

        return response([
            'success' => true,
            'cobro' => $cobro,
            'alerta' => $alerta,
            'estadoDeCuenta' => $estadoDeCuenta,
        ]);
    }

    public function consultacobrosanulados()
    {
        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Fecha' => date('Y-m-d'), //date('Y-m-d')
        );
        $webservice = new WebserviceController;
        $estadoDeCuenta = $webservice->cobranzas('ConsultaCobrosAnulados', $parameters);
        //        echo '<pre>';
        //        print_r($estadoDeCuenta['ConsultaCobrosAnuladosResult']['CONSULTA_COBROS_ANULADOS']);
        //        die();
        if (isset($estadoDeCuenta['ConsultaCobrosAnuladosResult']['CONSULTA_COBROS_ANULADOS'])) {
            $cobrosAnulados = $estadoDeCuenta['ConsultaCobrosAnuladosResult']['CONSULTA_COBROS_ANULADOS'];
            foreach ($cobrosAnulados as $anulado) {
                $cobro = GpCobro::where('numero_recibo', substr($anulado['Recibo'], 0, 11))->first();
                if ($cobro) {
                    $tipo = substr($anulado['Recibo'], 11);
                    if ($tipo == '-EFE') {
                        $eliminar = GpCobrosEfectivoMonto::where('cobro_id', $cobro->id)->first();
                        if ($eliminar) {
                            $gpUser = GpUsuario::find($cobro->user_id);
                            if ($gpUser) {
                                $gpUser->notify(new UserNotification('Efectivo anulado', 'El cobro en efectivo del recibo ' . $cobro->numero_recibo . ' ha sido anulado desde GP'));
                            }
                            $eliminar->delete();
                        }
                    }
                    $ret = substr($tipo, 0, 1);
                    if ($ret == 'I' || $ret == 'R') {
                        $id_ret = substr($tipo, 1);
                        $retencion = GpCobrosRetenciones::where('cobro_id', $cobro->id)
                            ->where('id', $id_ret)->first();
                        if ($retencion) {
                            $gpUser = GpUsuario::find($cobro->user_id);
                            if ($gpUser) {
                                $gpUser->notify(new UserNotification('Retención anulada', 'La retención ' . $retencion->ret_comprobante . ' del recibo ' . $cobro->numero_recibo . ' ha sido anulada desde GP'));
                            }
                            $retencion->delete();
                        }
                    }

                    $dep = substr($tipo, 0, 1);
                    if ($dep == 'D') {
                        $id_dep = substr($tipo, 1);
                        $deposito = GpCobrosDeposito::where('cobro_id', $cobro->id)
                            ->where('id', $id_dep)->first();
                        if ($deposito) {
                            $gpUser = GpUsuario::find($cobro->user_id);
                            if ($gpUser) {
                                $gpUser->notify(new UserNotification('Depósito anulado', 'El depósito ' . $deposito->comprobante . ' del recibo ' . $cobro->numero_recibo . ' ha sido anulado desde GP'));
                            }
                            $deposito->delete();
                        }
                    }

                    if ($tipo == '-CHE') {
                        $cheque = GpCobrosChequesMonto::where('cobro_id', $cobro->id)->first();

                        if ($cheque) {
                            $gpUser = GpUsuario::find($cobro->user_id);
                            if ($gpUser) {
                                $gpUser->notify(new UserNotification('Cheques anulados', 'El/los cheque/s del cobro ' . $cobro->numero_recibo . ' ha/n sido anulado/s desde GP'));
                            }
                            $cheque->delete();
                            GpCobrosCheques::where('cobro_id', $cobro->id)->delete();
                            GpCobrosChequesFacturas::where('cobro_id', $cobro->id)->delete();
                        }
                    }

                    if ($tipo == '-TC') {
                        $tarjeta = GpCobrosTarjetaMonto::where('cobro_id', $cobro->id)->first();
                        if ($tarjeta) {
                            $gpUser = GpUsuario::find($cobro->user_id);
                            if ($gpUser) {
                                $gpUser->notify(new UserNotification('Cobro con tarjeta anulado', 'El cobro con tarjeta ' . $tarjeta->comprobante . ' del recibo ' . $cobro->numero_recibo . ' ha sido anulado desde GP'));
                            }
                            $tarjeta->delete();

                            GpCobrosTarjetaFactura::where('cobro_id', $cobro->id)->delete();
                        }
                    }
                }
            }
        }
        //abort(404);
    }

    public function checkcobrosenviados()
    {

        $cobros = GpCobro::where('estado', '!=', 'enviado')->get();

        foreach ($cobros as $cobro) {
            $enviado = true;
            $retenciones = GpCobrosRetenciones::where('cobro_id', $cobro->id)->get();
            foreach ($retenciones as $retencion) {
                if ($retencion->ret_porcentaje_iva > 0 && $retencion->mensaje_ws_iva != 'Registro de cobro guardado correctamente.' && $retencion->mensaje_ws_iva != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
                if ($retencion->ret_porcentaje_renta != 0 && $retencion->mensaje_ws_renta != 'Registro de cobro guardado correctamente.' && $retencion->mensaje_ws_renta != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
            }

            $efectivos = GpCobrosEfectivoMonto::where('cobro_id', $cobro->id)->get();
            foreach ($efectivos as $efectivo) {
                if ($efectivo->mensaje_ws != 'Registro de cobro guardado correctamente.' && $efectivo->mensaje_ws != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
            }

            $depositos = GpCobrosDeposito::where('cobro_id', $cobro->id)->get();
            foreach ($depositos as $deposito) {
                if ($deposito->mensaje_ws != 'Registro de cobro guardado correctamente.' && $deposito->mensaje_ws != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
            }

            $cheques = GpCobrosChequesMonto::where('cobro_id', $cobro->id)->get();
            foreach ($cheques as $cheque) {
                if ($cheque->mensaje_ws != 'Registro de cobro guardado correctamente.' && $cheque->mensaje_ws != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
            }

            $tarjetas = GpCobrosTarjetaMonto::where('cobro_id', $cobro->id)->get();
            foreach ($tarjetas as $tarjeta) {
                if ($tarjeta->mensaje_ws != 'Registro de cobro guardado correctamente.' && $tarjeta->mensaje_ws != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
            }

            $aplicaciones = GpCobrosAplicacionMonto::where('cobro_id', $cobro->id)->get();
            foreach ($aplicaciones as $aplicacion) {
                if ($aplicacion->mensaje_ws != 'Registro de cobro guardado correctamente.' && $aplicacion->mensaje_ws != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
            }

            if ($enviado) {
                $cobro->estado = 'enviado';
                $cobro->save();
            }
        }
    }

    public function detallecobro($cobro)
    {
        $total_cobro = 0;
        $total_aplicado = 0;
        $retenciones = GpCobrosRetenciones::where('cobro_id', $cobro['id'])->get()->toArray();
        if ($retenciones) {
            foreach ($retenciones as $ret) {
                $total_cobro += $ret['total'];
                $total_aplicado += $ret['total'];
            }
            $cobro['retenciones'] = $retenciones;
        }

        $efectivo = GpCobrosEfectivoMonto::where('cobro_id', $cobro['id'])->get()->first();
        if ($efectivo) {
            $cobro['efectivo'] = $efectivo->toArray();
            $total_cobro += $cobro['efectivo']['monto'];
            $efectivo_facturas = GpCobrosEfectivoFactura::where('cobro_id', $cobro['id'])->get()->toArray();
            foreach ($efectivo_facturas as $factura) {
                $total_aplicado += $factura['monto'];
            }
            $cobro['efectivo_facturas'] = $efectivo_facturas;
        }

        $depositos = GpCobrosDeposito::join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', 'gp_cobros_depositos.banco')
            ->where('cobro_id', $cobro['id'])
            ->select('gp_cobros_depositos.*', 'gp_caja_bancos.CB_DESCRIPCION')
            ->get()->toArray();
        foreach ($depositos as $key => $dep) {
            $total_cobro += $dep['monto'];
            $depositos[$key]['facturas'] = GpCobrosDepositosFacturas::where('deposito_id', $dep['id'])->get()->toArray();
            foreach ($depositos[$key]['facturas'] as $factura) {
                $total_aplicado += $factura['monto'];
            }
        }
        $cobro['depositos'] = $depositos;

        $cheques = GpCobrosCheques::join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', 'gp_cobros_cheques.banco')
            ->where('cobro_id', $cobro['id'])
            ->select('gp_cobros_cheques.*', 'gp_caja_bancos.CB_DESCRIPCION')
            ->get()->toArray();

        foreach ($cheques as $cheque) {
            $total_cobro += $cheque['monto'];
        }

        $cheques_facturas = GpCobrosChequesFacturas::where('cobro_id', $cobro['id'])->get()->toArray();

        foreach ($cheques_facturas as $factura) {
            $total_aplicado += $factura['monto'];
        }

        $cobro['cheques'] = $cheques;
        $cobro['cheques_facturas'] = $cheques_facturas;

        $tarjeta = GpCobrosTarjetaMonto::join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', 'gp_cobros_tarjeta_montos.banco')
            ->where('cobro_id', $cobro['id'])
            ->select('gp_cobros_tarjeta_montos.*', 'gp_caja_bancos.CB_DESCRIPCION')
            ->get()->first();
        if ($tarjeta) {
            $cobro['tarjeta'] = $tarjeta->toArray();
            $total_cobro += $cobro['tarjeta']['monto'];
            $tarjeta_facturas = GpCobrosTarjetaFactura::where('cobro_id', $cobro['id'])->get()->toArray();
            foreach ($tarjeta_facturas as $factura) {
                $total_aplicado += $factura['monto'];
            }
            $cobro['tarjeta_facturas'] = $tarjeta_facturas;
        }

        $aplicaciones = GpCobrosAplicacionMonto::where('cobro_id', $cobro['id'])->get()->toArray();
        foreach ($aplicaciones as $key => $apl) {
            $total_cobro += $apl['monto'];
            $aplicaciones[$key]['facturas'] = GpCobrosAplicacionFactura::where('aplicacion_id', $apl['id'])->get()->toArray();
            foreach ($aplicaciones[$key]['facturas'] as $factura) {
                $total_aplicado += $factura['monto'];
            }
        }
        $cobro['aplicaciones'] = $aplicaciones;

        $cliente = GpCliente::where('id', $cobro['cliente_id'])->get()->first()->toArray();
        $cobro['cliente'] = $cliente;

        $puntodeventa = GpRelacionPuntoVentaCaja::join('gp_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_PUNTO_VENTA')
            ->where('gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_CAJA', $cobro['caja'])
            ->select('gp_punto_ventas.PTO_VTA_DESCRIPCION')->get()->first()->toArray();
        $cobro['puntodeventa'] = $puntodeventa['PTO_VTA_DESCRIPCION'];

        $usuario = GpUsuario::where('id', $cobro['user_id'])->get()->first()->toArray();
        $cobro['usuario'] = $usuario['USERNAME'];

        $cobro['total_cobro'] = number_format($total_cobro, '2', '.', '');
        $cobro['total_aplicado'] = number_format($total_aplicado, '2', '.', '');
        $cobro['saldo'] = number_format($total_cobro - $total_aplicado, '2', '.', '');
        return $cobro;
    }

    public function imprimircobro($id): \Illuminate\Http\Response
    {

        $cobro = GpCobro::where('id', $id)->get()->first()->toArray();

        $cobro = $this->detallecobro($cobro);
        //        echo '<pre>';
        //        print_r($cobro);
        //        die();
        //return view('cobranzaPDF', $cobro);
        $pdf = PDF::loadView('cobranzaPDF', $cobro);
        return $pdf->stream('cobro-' . $cobro['numero_recibo'] . '.pdf');
    }

    public function getcajausuario($puntodeventa, $userId): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $user = Auth::user();

        $caja = GpRelacionUsuarioCaja::join('gp_cajas', 'gp_cajas.CAJA_CODIGO', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->join('gp_relacion_punto_venta_cajas', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_CAJA', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->where('CAJ_USER_USUARIO', $user->USERID)
            ->where('gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_PUNTO_VENTA', $puntodeventa)
            ->select('gp_cajas.*')
            ->first();

        if (!$caja) {
            return response([
                'error' => 'No existe caja asignada al usuario en este punto de venta'
            ], 404);
        }

        return response([
            'success' => true,
            'cajausuario' => $caja->CAJA_CODIGO,
        ]);
    }

    public function datacierredecaja($fecha, $caja): array
    {

        $data = array();

        $data['fecha'] = $fecha;


        $user = Auth::user();

        $usuario = GpUsuario::join('gp_relacion_usuario_cajas', 'gp_relacion_usuario_cajas.CAJ_USER_USUARIO', 'gp_usuarios.USERID')
            ->join('gp_relacion_punto_venta_cajas', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_CAJA', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->join('gp_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_PUNTO_VENTA')
            ->select('gp_usuarios.*', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA', 'gp_punto_ventas.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_DESCRIPCION')
            ->where('gp_relacion_usuario_cajas.CAJ_USER_CAJA', $caja);
        if ($user->USERID !== 'adminWeb') {
            $usuario = $usuario->where('gp_usuarios.USERID', '!=', 'adminWeb');
        }
        $usuario = $usuario->get()->first()->toArray();


        $data['usuario'] = $usuario;

        $cierreDeCaja = GpCierreCaja::where('CAJA_CODIGO', $caja)
            ->where('fecha', $fecha)
            ->first();

        if ($cierreDeCaja) {
            $cierreDeCaja->datos = null;
            $cierreDeCaja->consulta = null;
        }

        $data['cierreDeCaja'] = $cierreDeCaja;

        $cobros = GpCobro::where('caja', $caja)
            ->whereDate('fecha', $fecha)
            ->get();


        $total = 0;
        // RETENCIONES
        $ret_iva = 0;
        $ret_renta = 0;
        foreach ($cobros as $cobro) {
            $retenciones = GpCobrosRetenciones::where('cobro_id', $cobro->id)
                ->where(function ($q) {
                    $q->where('mensaje_ws_iva', '=', 'Registro de cobro guardado correctamente.')
                        ->orWhere('mensaje_ws_iva', '=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
                })
                ->where(function ($q) {
                    $q->where('mensaje_ws_renta', '=', 'Registro de cobro guardado correctamente.')
                        ->orWhere('mensaje_ws_renta', '=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
                })
                ->get();
            foreach ($retenciones as $retencion) {
                $ret_iva += $retencion->ret_iva;
                $ret_renta += $retencion->ret_renta;
            }
        }
        $data['retenciones'] = array(
            'ret_iva' => number_format($ret_iva, 2, '.', ''),
            'ret_renta' => number_format($ret_renta, 2, '.', '')
        );

        $total += (float) $ret_iva;
        $total += (float) $ret_renta;
        //EFECTIVO
        $efectivo = 0;
        foreach ($cobros as $cobro) {
            $efectivos = GpCobrosEfectivoMonto::where('cobro_id', $cobro->id)
                //                ->where(function($q) {
                //                    $q->where('mensaje_ws', '=', 'Registro de cobro guardado correctamente.')
                //                        ->orWhere('mensaje_ws', '=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
                //                })
                ->get();
            foreach ($efectivos as $efe) {
                $efectivo += $efe->monto;
            }
        }
        $data['efectivo'] = number_format($efectivo, 2, '.', '');

        $total += (float) $data['efectivo'];

        //Cheques al día
        $valor_cheques = 0;
        foreach ($cobros as $cobro) {
            $cheques = GpCobrosCheques::join('gp_cobros_cheques_montos', 'gp_cobros_cheques_montos.cobro_id', 'gp_cobros_cheques.cobro_id')
                ->where('gp_cobros_cheques.cobro_id', $cobro->id)
                ->whereDate('gp_cobros_cheques.fecha_vencimiento', $fecha)
                ->where(function ($q) {
                    $q->where('gp_cobros_cheques_montos.mensaje_ws', '=', 'Registro de cobro guardado correctamente.')
                        ->orWhere('gp_cobros_cheques_montos.mensaje_ws', '=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
                })
                ->select('gp_cobros_cheques.*')
                ->get();
            foreach ($cheques as $cheque) {
                $valor_cheques += $cheque->monto;
            }
        }
        $data['cheques'] = number_format($valor_cheques, 2, '.', '');
        $total += (float) $data['cheques'];

        //Cheques postfechados
        $valor_cheques_p = 0;
        foreach ($cobros as $cobro) {
            $cheques = GpCobrosCheques::join('gp_cobros_cheques_montos', 'gp_cobros_cheques_montos.cobro_id', 'gp_cobros_cheques.cobro_id')
                ->where('gp_cobros_cheques.cobro_id', $cobro->id)
                ->whereDate('gp_cobros_cheques.fecha_vencimiento', '>', $fecha)
                ->where(function ($q) {
                    $q->where('gp_cobros_cheques_montos.mensaje_ws', '=', 'Registro de cobro guardado correctamente.')
                        ->orWhere('gp_cobros_cheques_montos.mensaje_ws', '=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
                })
                ->select('gp_cobros_cheques.*')
                ->get();
            foreach ($cheques as $cheque) {
                $valor_cheques_p += $cheque->monto;
            }
        }
        $data['cheques_p'] = number_format($valor_cheques_p, 2, '.', '');
        $total += (float) $data['cheques_p'];

        //Depósitos
        $valor_depositos = 0;
        foreach ($cobros as $cobro) {
            $depositos = GpCobrosDeposito::where('cobro_id', $cobro->id)
                ->where('estado', '!=', 'iniciado')
                //                ->where(function($q) {
                //                    $q->where('mensaje_ws', '=', 'Registro de cobro guardado correctamente.')
                //                        ->orWhere('mensaje_ws', '=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
                //                })
                ->get();
            foreach ($depositos as $deposito) {
                $valor_depositos += $deposito->monto;
            }
        }
        $data['depositos'] = number_format($valor_depositos, 2, '.', '');
        $total += (float) $data['depositos'];

        //Tarjeta de crédito
        $valor_tarjetas = 0;
        foreach ($cobros as $cobro) {
            $tarjetas = GpCobrosTarjetaMonto::where('cobro_id', $cobro->id)
                //                ->where(function($q) {
                //                    $q->where('mensaje_ws', '=', 'Registro de cobro guardado correctamente.')
                //                        ->orWhere('mensaje_ws', '=', 'El número de recibo de cobro enviado ya existe para esta empresa.');
                //                })
                ->get();
            foreach ($tarjetas as $tarjeta) {
                $valor_tarjetas += $tarjeta->monto;
            }
        }
        $data['tarjetas'] = number_format($valor_tarjetas, 2, '.', '');
        $total += (float) $data['tarjetas'];

        $data['total'] = number_format($total, 2, '.', '');

        $data['fecha'] = date('Y-m-d H:m:i');

        return $data;
    }

    public function imprimircierredecaja($fecha, $caja): \Illuminate\Http\Response
    {
        $data = GpCierreCaja::where('CAJA_CODIGO', $caja)
            ->where('fecha', $fecha)
            ->first();
        $data = json_decode($data->datos, true);

        $pdf = PDF::loadView('cierredecajaPDF', $data);
        return $pdf->stream('cierre-de-caja-' . $data['usuario']['USERID'] . '.pdf');
    }

    public function imprimircierredecajausuario($fecha, $caja): \Illuminate\Http\Response
    {

        $resumen = GpCierreCaja::where('CAJA_CODIGO', $caja)
            ->where('fecha', $fecha)
            ->where('estado', 'cerrado')
            ->first();
        if (!$resumen) {
            $data = $this->cierrecajausuario($fecha, $caja);
        } elseif ($resumen->consulta) {
            $data = json_decode($resumen->consulta, true);
        } else {
            $data = $this->cierrecajausuario($fecha, $caja);
        }

        //return view('cierredecajausuarioPDF', $data);
        $pdf = PDF::loadView('cierredecajausuarioPDF', $data);
        return $pdf->stream('consulta-de-caja-usuario-' . $caja . '.pdf');
    }


    public function resumencaja($fecha, $caja, $userId): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $resumen = GpCierreCaja::where('CAJA_CODIGO', $caja)
            ->where('fecha', $fecha)
            ->where('estado', 'cerrado')
            ->first();
        if (!$resumen) {
            $data = $this->datacierredecaja($fecha, $caja);
        } else {
            $data = json_decode($resumen->datos, true);
        }

        return response([
            'success' => true,
            'caja' => $data,
        ]);
    }

    public function consultaretencion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'comprobante' => 'required',
            'cliente_id' => 'required',
        ]);

        $cobro = GpCobrosRetenciones::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_retenciones.cobro_id')
            ->where('gp_cobros.cliente_id', $data['cliente_id'])
            ->where('gp_cobros_retenciones.ret_comprobante', $data['comprobante'])
            ->first();

        if ($cobro) {
            $response = [
                'existe' => true,
                'cobro' => $cobro,
            ];
        } else {
            $response = [
                'existe' => false,
            ];
        }

        return response(
            $response
        );
    }

    public function consultacheque(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'cheque' => [],
            'cliente_id' => 'required',
        ]);

        $cobro = GpCobrosCheques::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_cheques.cobro_id')
            //->where('gp_cobros.cliente_id',$data['cliente_id'])
            ->where('gp_cobros_cheques.cuenta', $data['cheque']['cuenta'])
            ->where('gp_cobros_cheques.cheque', $data['cheque']['cheque'])
            ->first();

        if ($cobro) {
            $response = [
                'existe' => true,
                'cobro' => $cobro,
            ];
        } else {
            $response = [
                'existe' => false,
            ];
        }

        return response(
            $response
        );
    }

    public function consultadeposito(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'deposito' => 'required',
            'banco' => 'required',
            'cliente_id' => 'required',
        ]);

        $cobro = GpCobrosDeposito::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_depositos.cobro_id')
            //->where('gp_cobros.cliente_id',$data['cliente_id'])
            ->where('gp_cobros_depositos.banco', $data['banco'])
            ->where('gp_cobros_depositos.comprobante', $data['deposito'])
            ->select('gp_cobros.numero_recibo', 'gp_cobros_depositos.*')
            ->first();

        if ($cobro) {
            $response = [
                'existe' => true,
                'cobro' => $cobro,
            ];
        } else {
            $response = [
                'existe' => false,
            ];
        }

        return response(
            $response
        );
    }

    public function estadocaja($fechaActual = null, $userId): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $user = Auth::user();

        if (!$fechaActual)
            $fechaActual = date('Y-m-d');

        if (strlen($fechaActual) == 3) {
            $puntodenta = $fechaActual;
            $caja = GpRelacionUsuarioCaja::join('gp_cajas', 'gp_cajas.CAJA_CODIGO', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
                ->join('gp_relacion_punto_venta_cajas', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_CAJA', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
                ->where('CAJ_USER_USUARIO', $user->USERID)
                ->where('gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_PUNTO_VENTA', $puntodenta)
                ->select('gp_cajas.*')
                ->first();
        } else {
            $caja = GpRelacionUsuarioCaja::join('gp_cajas', 'gp_cajas.CAJA_CODIGO', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
                ->where('CAJ_USER_USUARIO', $user->USERID)
                ->select('gp_cajas.*')
                ->first();
        }

        if (!$caja) {
            return response([
                'error' => 'No existe caja asignada al usuario en este punto de venta'
            ], 404);
        }

        if (strlen($fechaActual) == 3)
            $fechaActual = date('Y-m-d');

        $cierreCaja = GpCierreCaja::where('CAJA_CODIGO', $caja->CAJA_CODIGO)
            ->where('fecha', $fechaActual)
            ->where('estado', 'abierto')
            ->first();

        if ($cierreCaja) {
            $caja->estado = 'abierto';
        } else {
            $cierreCaja = GpCierreCaja::where('CAJA_CODIGO', $caja->CAJA_CODIGO)
                ->where('fecha', $fechaActual)
                ->where('estado', 'cerrado')
                ->first();
            if ($cierreCaja) {
                $caja->estado = 'cerrado';
            } else {
                $estadoAnterior = GpCierreCaja::where('CAJA_CODIGO', $caja->CAJA_CODIGO)
                    ->where('fecha', '<', $fechaActual)
                    ->where('estado', 'abierto')
                    ->first();

                if ($estadoAnterior) {
                    $caja->estado = 'sin cierre';
                } else {
                    $caja->estado = 'cerrado';
                }
            }
        }

        return response([
            'success' => true,
            'caja' => $caja,
        ]);
    }

    public function abrircaja($puntodeventa, $userId)
    {
        $user = Auth::user();

        $caja = GpRelacionUsuarioCaja::join('gp_cajas', 'gp_cajas.CAJA_CODIGO', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->join('gp_relacion_punto_venta_cajas', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_CAJA', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->where('CAJ_USER_USUARIO', $user->USERID)
            ->where('gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_PUNTO_VENTA', $puntodeventa)
            ->select('gp_cajas.*')
            ->first();

        $reabrirCaja = GpCierreCaja::where('CAJA_CODIGO', $caja->CAJA_CODIGO)
            ->where('fecha', date('Y-m-d'))
            ->first();

        if ($reabrirCaja) {
            $reabrirCaja->estado = 'abierto';
            $reabrirCaja->fecha_reapertura = date('Y-m-d H:i:s');
            $reabrirCaja->save();
        } else {
            $apertura = new GpCierreCaja();
            $apertura->CAJA_CODIGO = $caja->CAJA_CODIGO;
            $apertura->fecha = date('Y-m-d');
            $apertura->estado = 'abierto';
            $apertura->fecha_apertura = date('Y-m-d H:i:s');
            $apertura->save();
        }
    }

    public function cerrarcaja($puntodeventa, $userId): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $user = Auth::user();

        $caja = GpRelacionUsuarioCaja::join('gp_cajas', 'gp_cajas.CAJA_CODIGO', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->join('gp_relacion_punto_venta_cajas', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_CAJA', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA')
            ->where('CAJ_USER_USUARIO', $user->USERID)
            ->where('gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_PUNTO_VENTA', $puntodeventa)
            ->select('gp_cajas.*')
            ->first();

        $estadoCaja = GpCierreCaja::where('CAJA_CODIGO', $caja->CAJA_CODIGO)
            ->where('fecha', date('Y-m-d'))
            ->where('estado', 'abierto')
            ->first();

        if ($estadoCaja) {

            $fechaUltimaCaja = $estadoCaja->fecha;

            $estadoCaja->estado = 'cerrado';
            $estadoCaja->fecha_cierre = date('Y-m-d H:i:s');

            $data = $this->datacierredecaja(date('Y-m-d'), $caja->CAJA_CODIGO);
            $consulta = $this->cierrecajausuario(date('Y-m-d'), $caja->CAJA_CODIGO);
            $estadoCaja->datos = json_encode($data);
            $estadoCaja->consulta = json_encode($consulta);

            $estadoCaja->save();
        } else {
            $cajaAnterior = GpCierreCaja::where('CAJA_CODIGO', $caja->CAJA_CODIGO)
                ->where('estado', 'abierto')
                ->orderBy('fecha', 'DESC')
                ->first();

            $fechaUltimaCaja = $cajaAnterior->fecha;

            $cajaAnterior->estado = 'cerrado';
            $cajaAnterior->fecha_cierre = date('Y-m-d H:i:s');

            $data = $this->datacierredecaja(substr($fechaUltimaCaja, 0, 10), $caja->CAJA_CODIGO);
            $consulta = $this->cierrecajausuario(substr($fechaUltimaCaja, 0, 10), $caja->CAJA_CODIGO);
            $cajaAnterior->datos = json_encode($data);
            $cajaAnterior->consulta = json_encode($consulta);

            $cajaAnterior->save();
        }
        return response([
            'success' => true,
            'mensaje' => 'Cierre de caja procesado',
            'fecha' => $fechaUltimaCaja,
        ]);
    }

    public function consultacierrecajausuario(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $resumen = GpCierreCaja::where('CAJA_CODIGO', $data['caja_usuario'])
            ->where('fecha', $data['fecha'])
            ->where('estado', 'cerrado')
            ->first();
        if (!$resumen) {
            $return = $this->cierrecajausuario($data['fecha'], $data['caja_usuario']);
        } elseif ($resumen->consulta) {
            $return = json_decode($resumen->consulta, true);
        } else {
            $return = $this->cierrecajausuario($data['fecha'], $data['caja_usuario']);
        }

        return response([
            'success' => true,
            'mensaje' => 'Cierre de caja procesado',
            'data' => $return,
        ]);
    }

    public function cierrecajausuario($fecha, $caja_usuario): array|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
    {
        $cobranzas = array();

        $cobranzas['caja_usuario'] = $caja_usuario;
        $cobranzas['fecha'] = $fecha;

        $usuario = GpUsuario::join('gp_relacion_usuario_cajas', 'gp_relacion_usuario_cajas.CAJ_USER_USUARIO', 'gp_usuarios.USERID')
            ->join('gp_relacion_usuario_punto_ventas', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_USUARIO', 'gp_usuarios.USERID')
            ->join('gp_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA')
            ->select('gp_usuarios.*', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA', 'gp_punto_ventas.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_DESCRIPCION')
            ->where('gp_relacion_usuario_cajas.CAJ_USER_CAJA', $cobranzas['caja_usuario'])
            ->get()->first()->toArray();
        $cobranzas['usuario'] = $usuario;

        $aplicacion_saldos = GpCobrosAplicacionMonto::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_aplicacion_montos.cobro_id')
            ->join('gp_clientes', 'gp_clientes.id', 'gp_cobros.cliente_id')
            ->where('gp_cobros.caja', $cobranzas['caja_usuario'])
            ->whereDate('gp_cobros.fecha', $cobranzas['fecha'])
            ->select('gp_cobros_aplicacion_montos.*', 'gp_cobros.numero_recibo', 'gp_cobros.fecha', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC')
            ->get();

        foreach ($aplicacion_saldos as $ap) {
            $ap->aplicado = 0;
            $aplicaciones = GpCobrosAplicacionFactura::where('cobro_id', $ap->cobro_id)->get();
            foreach ($aplicaciones as $factura) {
                $ap->aplicado += number_format($factura->monto, 2, '.', '');
            }
        }

        $cobranzas['aplicacion_saldos'] = $aplicacion_saldos;

        $total = 0;
        foreach ($aplicacion_saldos as $value) {
            $total += $value->monto;
        }
        $cobranzas['aplicacion_saldos_total'] = number_format($total, 2, '.', '');

        $depositos = GpCobrosDeposito::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_depositos.cobro_id')
            ->join('gp_clientes', 'gp_clientes.id', 'gp_cobros.cliente_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', 'gp_cobros_depositos.banco')
            ->where('gp_cobros.caja', $cobranzas['caja_usuario'])
            ->whereDate('gp_cobros.fecha', $cobranzas['fecha'])
            ->select(
                'gp_cobros_depositos.*',
                'gp_cobros_depositos.fecha AS fecha_deposito',
                'gp_cobros_depositos.mensaje_ws',
                'gp_cobros.numero_recibo',
                'gp_cobros.fecha',
                'gp_clientes.CLI_RAZON_SOCIAL',
                'gp_clientes.CLI_RUC',
                'gp_caja_bancos.CB_DESCRIPCION'
            )
            ->get();

        foreach ($depositos as $dep) {
            $dep->aplicado = 0;
            $aplicaciones = GpCobrosDepositosFacturas::where('cobro_id', $dep->cobro_id)->get();
            foreach ($aplicaciones as $factura) {
                $dep->aplicado += number_format($factura->monto, 2, '.', '');
            }
        }

        $cobranzas['depositos'] = $depositos;

        $total = 0;
        foreach ($depositos as $value) {
            $total += $value->monto;
        }
        $cobranzas['depositos_total'] = number_format($total, 2, '.', '');

        $cheques_aldia = GpCobrosCheques::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_cheques.cobro_id')
            ->join('gp_clientes', 'gp_clientes.id', 'gp_cobros.cliente_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', 'gp_cobros_cheques.banco')
            ->where('gp_cobros.caja', $cobranzas['caja_usuario'])
            ->where(DB::raw("DATE(gp_cobros.fecha)"), "=", DB::raw("DATE(gp_cobros_cheques.fecha_vencimiento)"))
            ->whereDate('gp_cobros.fecha', $cobranzas['fecha'])
            ->select('gp_cobros_cheques.*', 'gp_cobros.numero_recibo', 'gp_cobros.fecha', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC', 'gp_caja_bancos.CB_DESCRIPCION')
            ->get();

        foreach ($cheques_aldia as $che) {
            $che->aplicado = 0;
            $aplicaciones = GpCobrosChequesFacturas::where('cobro_id', $che->cobro_id)->get();
            foreach ($aplicaciones as $factura) {
                $che->aplicado += number_format($factura->monto, 2, '.', '');
            }
        }

        $cobranzas['cheques_aldia'] = $cheques_aldia;

        $total = 0;
        foreach ($cheques_aldia as $value) {
            $total += $value->monto;
        }
        $cobranzas['cheques_aldia_total'] = number_format($total, 2, '.', '');

        $cheques_posfechados = GpCobrosCheques::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_cheques.cobro_id')
            ->join('gp_clientes', 'gp_clientes.id', 'gp_cobros.cliente_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', 'gp_cobros_cheques.banco')
            ->where('gp_cobros.caja', $cobranzas['caja_usuario'])
            ->where(DB::raw("DATE(gp_cobros.fecha)"), "<", DB::raw("DATE(gp_cobros_cheques.fecha_vencimiento)"))
            ->whereDate('gp_cobros.fecha', $cobranzas['fecha'])
            ->select('gp_cobros_cheques.*', 'gp_cobros.numero_recibo', 'gp_cobros.fecha', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC', 'gp_caja_bancos.CB_DESCRIPCION')
            ->get();

        foreach ($cheques_posfechados as $che) {
            $che->aplicado = 0;
            $aplicaciones = GpCobrosChequesFacturas::where('cobro_id', $che->cobro_id)->get();
            foreach ($aplicaciones as $factura) {
                $che->aplicado += number_format($factura->monto, 2, '.', '');
            }
        }
        $cobranzas['cheques_posfechados'] = $cheques_posfechados;

        $total = 0;
        foreach ($cheques_posfechados as $value) {
            $total += $value->monto;
        }
        $cobranzas['cheques_posfechados_total'] = number_format($total, 2, '.', '');

        $efectivo = GpCobrosEfectivoMonto::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_efectivo_montos.cobro_id')
            ->join('gp_clientes', 'gp_clientes.id', 'gp_cobros.cliente_id')
            ->where('gp_cobros.caja', $cobranzas['caja_usuario'])
            ->whereDate('gp_cobros.fecha', $cobranzas['fecha'])
            ->select('gp_cobros_efectivo_montos.*', 'gp_cobros.numero_recibo', 'gp_cobros.fecha', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC')
            ->get();

        foreach ($efectivo as $efe) {
            $efe->aplicado = 0;
            $aplicaciones = GpCobrosEfectivoFactura::where('cobro_id', $efe->cobro_id)->get();
            foreach ($aplicaciones as $factura) {
                $efe->aplicado += number_format($factura->monto, 2, '.', '');
            }
        }

        $cobranzas['efectivo'] = $efectivo;

        $total = 0;
        foreach ($efectivo as $value) {
            $total += $value->monto;
        }
        $cobranzas['efectivo_total'] = number_format($total, 2, '.', '');

        $retenciones = GpCobrosRetenciones::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_retenciones.cobro_id')
            ->join('gp_clientes', 'gp_clientes.id', 'gp_cobros.cliente_id')
            ->where('gp_cobros.caja', $cobranzas['caja_usuario'])
            ->whereDate('gp_cobros.fecha', $cobranzas['fecha'])
            ->select('gp_cobros_retenciones.*', 'gp_cobros.numero_recibo', 'gp_cobros.fecha', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC')
            ->get();
        $cobranzas['retenciones'] = $retenciones;

        $total = 0;
        foreach ($retenciones as $value) {
            $total += $value->total;
        }
        $cobranzas['retenciones_total'] = number_format($total, 2, '.', '');

        $tarjeta = GpCobrosTarjetaMonto::join('gp_cobros', 'gp_cobros.id', 'gp_cobros_tarjeta_montos.cobro_id')
            ->join('gp_clientes', 'gp_clientes.id', 'gp_cobros.cliente_id')
            ->where('gp_cobros.caja', $cobranzas['caja_usuario'])
            ->whereDate('gp_cobros.fecha', $cobranzas['fecha'])
            ->select('gp_cobros_tarjeta_montos.*', 'gp_cobros.numero_recibo', 'gp_cobros.fecha', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC')
            ->get();

        foreach ($tarjeta as $tar) {
            $tar->aplicado = 0;
            $aplicaciones = GpCobrosEfectivoFactura::where('cobro_id', $tar->cobro_id)->get();
            foreach ($aplicaciones as $factura) {
                $tar->aplicado += number_format($factura->monto, 2, '.', '');
            }
        }

        $cobranzas['tarjeta'] = $tarjeta;

        $total = 0;
        foreach ($tarjeta as $value) {
            $total += $value->monto;
        }
        $cobranzas['tarjeta_total'] = number_format($total, 2, '.', '');

        return $cobranzas;
    }

    public function consultacobroscliente(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $cobros = $this->cobroscliente($data);

        return response([
            'success' => true,
            'mensaje' => 'Cierre de caja procesado',
            'cobros' => $cobros,
        ]);
    }

    public function cobroscliente($data): array|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
    {

        $cobros = GpCobro::where('cliente_id', $data['cliente']['id'])
            ->whereDate('fecha', '>=', $data['fecha_inicio'])
            ->whereDate('fecha', '<=', $data['fecha_fin'])
            ->orderBy('fecha', 'DESC')
            ->get()->toArray();

        foreach ($cobros as $key => $cobro) {
            $cobros[$key] = $this->detallecobro($cobro);
        }

        return $cobros;
    }


    public function listaPvCajasUsuario(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {

            // $user = $request->user();
            $user = GpUsuario::find(179);
            $response = $this->collectionService->listPointSalesByUSer($user);
            return $this->successResponse('Lista Obtenida', $response, 200);
        }, 'laravel', 'listaPvCajasUsuario');
    }


    public function listarCajasPorUsuarioPuntoVenta(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'punto_venta' => 'required',
            ]);
            $pv = data_get($validate, 'punto_venta');
            $user = GpUsuario::find(86);
            $response = $this->collectionService->listBoxesByPointSales($pv, $user);
            return $this->successResponse('Lista Obtenida', $response, 200);
        }, 'laravel', 'listarCajasDisponiblesPorUsuario');
    }


    public function consultarCierreCajaUsuario(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'fecha'     => 'required',
                'caja_id'   => 'required',

            ]);
            $fecha  = data_get($validate, 'fecha');
            $cajaId = data_get($validate, 'caja_id');
            $user   = GpUsuario::find(86);
            $response = $this->collectionService->getBoxClosureByDate($cajaId, $user, $fecha);
            return $this->successResponse('Consulta realizada', $response, 200);
        }, 'laravel', 'consultarCierreCajaUsuario');
    }
}
