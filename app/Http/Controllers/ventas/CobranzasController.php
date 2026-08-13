<?php

namespace App\Http\Controllers\ventas;

use App\Enums\Ventas\OrderStatus;
use App\Enums\Ventas\TaxRateEnum;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FuncionesController;
use App\Http\Controllers\WebserviceController;
use App\Models\GpCierreCaja;
use App\Models\GpCobrosAplicacionFactura;
use App\Models\GpCobrosAplicacionMonto;
use App\Models\GpCobrosTarjetaFactura;
use App\Models\GpCobrosTarjetaMonto;
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
use App\Http\Requests\Cobranzas\CobrosFacturas\RegisterCollectionRequest;
use App\Models\GpCaja;
use App\Models\GpPuntoVenta;
use App\Notifications\User\UserNotification;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionGpSenderServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;
use App\Services\Cobranzas\Contracts\CollectionUnapplicationServiceInterface;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;
use App\Services\Cobranzas\Depositos\Contracts\HistoricalDepositProcessorServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class CobranzasController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;



    public function __construct(
        protected readonly CollectionServiceInterface $collectionService,
        protected readonly CustomerPaymentApplicationServiceInterface $customer,
        protected readonly CollectionUnapplicationServiceInterface $unapplycation,
        protected readonly HistoricalDepositProcessorServiceInterface $historyDeposits,
        protected readonly InvoiceCollectionServiceInterface $invoiceCollect,
        protected readonly CollectionRegistrationServiceInterface $collectionRegistrationService,
        protected readonly CollectionGpSenderServiceInterface $sendCollectionService,
    ) {}
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


    public function depositosporaprobar(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $depositos = GpCobrosDeposito::where('monto', '>', 0)
            ->join('gp_cobros', 'gp_cobros.id', '=', 'gp_cobros_depositos.cobro_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', '=', 'gp_cobros_depositos.banco')
            ->join('gp_clientes', 'gp_clientes.id', '=', 'gp_cobros.cliente_id')
            ->leftJoin('gp_punto_ventas', 'gp_punto_ventas.id', '=', 'gp_cobros.pv_id')
            ->where('gp_cobros_depositos.estado', 'iniciado')
            ->orderBy('gp_cobros_depositos.fecha', 'DESC')
            ->get(['gp_cobros_depositos.*', 'gp_cobros.numero_recibo', 'gp_cobros.fecha as fecha_creacion_cobro', 'gp_punto_ventas.PTO_VTA_DESCRIPCION as agencia', 'gp_caja_bancos.CB_DESCRIPCION', 'gp_clientes.CLI_RUC', 'gp_clientes.CLI_RAZON_SOCIAL']);

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
        $user = $request->user();
        $deposito = GpCobrosDeposito::where('id', $request->id)->first();
        $deposito->fecha_aprobacion_rechazo = Carbon::now()->format('Y-m-d H:i:s');
        $deposito->user_aprobador_rechazo_id = $user->id;
        $deposito->estado = 'revisado';
        $deposito->save();

        // $this->enviarcobro($deposito->cobro_id);

        $cobro = GpCobro::where('id', $deposito->cobro_id)->first();
        $cobro->fecha_aprobacion_rechazo = Carbon::now()->format('Y-m-d H:i:s');
        $cobro->user_aprobador_rechazo_id = $user->id;
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

        $userCobro = GpUsuario::where('id', $cobro->user_id)->first();
        $mensaje = 'Depósito ' . $dato->comprobante . ' a la cuenta ' . $dato->CB_DESCRIPCION . ' del cliente ' . $dato->CLI_RAZON_SOCIAL . ' con RUC: ' . $dato->CLI_RUC . ' ha sido aprobado';
        if ($userCobro) {
            $userCobro->notify(new UserNotification('Depósito aprobado', $mensaje));
        }
        // $deposito->refresh();
        // SendCollectionToGpJob::dispatch($cobro->id);
        $this->sendCollectionService->sendCollectionToGp($cobro->id);

        return response([
            'success' => true,
            'message' => 'Depósito enviado'
        ]);
    }

    public function rechazardeposito(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data       = $request->all();
        $user       = $request->user();
        $deposito   = GpCobrosDeposito::where('id', $data['id'])->first();
        $deposito->user_aprobador_rechazo_id  = $user->id;
        $deposito->fecha_aprobacion_rechazo    = Carbon::now()->format('Y-m-d H:i:s');
        $deposito->mensaje_ws       =  data_get($data, 'motivo', null);
        $deposito->estado           = 'rechazado';
        $deposito->save();

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
        $cobro                              = GpCobro::where('id', $deposito->cobro_id)->first();
        $cobro->fecha_aprobacion_rechazo    = Carbon::now()->format('Y-m-d H:i:s');
        $cobro->user_aprobador_rechazo_id   = $user->id;
        $cobro->estado                      = 'rechazado';
        $cobro->save();
        $userCobro = GpUsuario::where('id', $cobro->user_id)->first();
        if ($userCobro) {
            $userCobro->notify(new UserNotification('Depósito/Transferencia rechazado', $mensaje));
        }
        return response([
            'success' => true,
            'message' => 'Depósito rechazado'
        ]);
    }

    public function enviarcobros()
    {
        return $this->buildErrorResponse('Api ya no funcional', 404);
    }

    // despues eliminar esta api


    public function consultacobrosanulados()
    {
        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Fecha' => date('Y-m-d'),
        );
        $webservice = new WebserviceController;
        $estadoDeCuenta = $webservice->cobranzas('ConsultaCobrosAnulados', $parameters);
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
                if (
                    ($retencion->ret_iva <= 0 || $retencion->enviado_iva_gp)
                    && ($retencion->ret_renta <= 0 || $retencion->enviado_renta_gp)
                    && ($retencion->ret_iva_srv <= 0 || $retencion->enviado_iva_srv_gp)
                    && ($retencion->ret_renta_srv <= 0 || $retencion->enviado_renta_srv_gp)
                ) {
                    continue;
                }

                if ($retencion->ret_iva > 0 && !$retencion->enviado_iva_gp) {
                    $enviado = false;
                }
                if ($retencion->ret_renta > 0 && !$retencion->enviado_renta_gp) {
                    $enviado = false;
                }
                if ($retencion->ret_iva_srv > 0 && !$retencion->enviado_iva_srv_gp) {
                    $enviado = false;
                }
                if ($retencion->ret_renta_srv > 0 && !$retencion->enviado_renta_srv_gp) {
                    $enviado = false;
                }

                if ($retencion->ret_porcentaje_iva > 0 && $retencion->mensaje_ws_iva != 'Registro de cobro guardado correctamente.' && $retencion->mensaje_ws_iva != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
                if ($retencion->ret_porcentaje_renta != 0 && $retencion->mensaje_ws_renta != 'Registro de cobro guardado correctamente.' && $retencion->mensaje_ws_renta != 'El número de recibo de cobro enviado ya existe para esta empresa.') {
                    $enviado = false;
                }
            }

            $efectivos = GpCobrosEfectivoMonto::where('cobro_id', $cobro->id)->get();
            foreach ($efectivos as $efectivo) {
                if (!$efectivo->enviado_gp) {
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

    //api migrada a servicio collectionInvoiceService
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
    // api migrada a servicio collectionInvoiceService
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
    // api migrada a servicio collectionInvoiceService
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
            ->where('gp_cobros_depositos.estado', '!=', 'rechazado')
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
        $modelCaja = GpCaja::get()->keyBy('CAJA_CODIGO');
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
            $apertura->caja_id = $modelCaja[$caja->CAJA_CODIGO]->id;
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
        $user = $request->user();
        $cobros = $this->cobroscliente($data, $user);

        return response([
            'success' => true,
            'mensaje' => 'Cierre de caja procesado',
            'cobros' => $cobros,
        ]);
    }

    public function cobroscliente($data, $user): array|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
    {
        $query = GpCobro::where('cliente_id', $data['cliente']['id'])
            ->whereDate('fecha', '>=', $data['fecha_inicio'])
            ->whereDate('fecha', '<=', $data['fecha_fin']);

        $query->when(!$this->hasFullAccess($user), function ($q) use ($data) {
            $ptoVta = data_get($data, 'puntoVenta');
            $pventa = GpPuntoVenta::where('PTO_VTA_ID', $ptoVta)->first();

            if ($pventa) {
                $cajas = $pventa->cajasDisponibles()->pluck('gp_cajas.id')->toArray();
                $q->whereIn('caja_id', $cajas);
            }
        });
        $cobros = $query->orderBy('fecha', 'DESC')
            ->get()->toArray();

        foreach ($cobros as $key => $cobro) {
            $cobros[$key] = $this->detallecobro($cobro);
        }

        return $cobros;
    }

    private function hasFullAccess($user): bool
    {
        return in_array($user->perfil_id, [1, 2, 18]) || $user->is_admin_cobros;
    }


    public function listaPvCajasUsuario(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $user = $request->user();
            $response = $this->collectionService->listPointSalesByUSer($user);
            return $this->successResponse('Lista Obtenida', $response, 200);
        }, 'laravel', 'listaPvCajasUsuario');
    }


    public function listarCajasPorUsuarioPuntoVenta(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'pv_id' => 'required',
            ]);
            $pvId = data_get($validate, 'pv_id');
            $user = $request->user();
            $response = $this->collectionService->listBoxesByPointSales($pvId, $user);
            return $this->successResponse('Lista Obtenida', $response, 200);
        }, 'laravel', 'listarCajasDisponiblesPorUsuario');
    }


    public function consultarCierreCajaUsuario(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'fecha'     => 'required|string',
                'caja_id'   => 'required|integer',
                'pv_id'     => 'required|integer',

            ]);
            $fecha  = data_get($validate, 'fecha');
            $cajaId = data_get($validate, 'caja_id');
            $response = $this->collectionService->getBoxClosureByDate($cajaId, $fecha);
            return $this->successResponse('Consulta realizada', $response, 200);
        }, 'laravel', 'consultarCierreCajaUsuario');
    }



    public function imprimirPdfConsultaCierreCaja(Request $request): Response
    {
        return $this->handleDocumentAction(function () use ($request): Response {
            $validate = $request->validate([
                'fecha'     => 'required|string',
                'caja_id'   => 'required|integer',
                'pv_id'     => 'required|integer',
            ]);
            $fecha  = data_get($validate, 'fecha');
            $cajaId = data_get($validate, 'caja_id');
            $pdf = $this->collectionService->generateCashClosurePdfByUser($cajaId, $fecha);
            return $this->buildFileResponse($pdf, 200);
        }, 'laravel', 'imprimirPdfConsultaCierreCaja');
    }

    public function imprimirPdfCierreCaja(Request $request): Response
    {
        return $this->handleDocumentAction(function () use ($request): Response {
            $validate = $request->validate([
                'fecha'     => 'required|string',
                'caja_id'   => 'required|integer',
                'pv_id'     => 'required|integer',
            ]);
            $fecha  = data_get($validate, 'fecha');
            $cajaId = data_get($validate, 'caja_id');
            $pdf = $this->collectionService->generateCashClosurePdf($cajaId, $fecha);
            return $this->buildFileResponse($pdf, 200);
        }, 'laravel', 'imprimirPdfCierreCaja');
    }

    /** funciones para la validacion de pagos de facturas  */

    public function obtenerInformacionPagosFacturas(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $payload    = $request->input('data', []);
            $user       = $request->user() ?? GpUsuario::find(179);
            $info       = $this->customer->validateIdentifyPaymentInvoices($payload, $user);
            return $this->successResponse('Información de Pagos Obtenido!', $info, 200);
        }, 'laravel', 'obtenerInformacionPagosFacturas');
    }

    /** function para listar los cobros que pueden ser desaplicados  */

    public function listaCobrosAplicados(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters  = $request->input('filters', []);
            $lista     = $this->unapplycation->fetchAppliedCollections($filters);
            return $this->successResponse('Lista obtenida', $lista, 200);
        }, 'laravel', 'listaCobrosPorDesaplicar');
    }

    public function detalleCobrosAplicados(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $ruc = $request->input('ruc');
            $factura = $request->input('factura');
            $user = $request->user();
            $data = $this->unapplycation->getAllDetailCollection($ruc, $factura, $user);
            return $this->successResponse('Obtenido con Éxito', $data, 200);
        }, 'laravel', 'detaleCobrosAplicados');
    }

    public function procesarDesaplicaciones(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $items = $request->input('payload', []);
            $user = $request->user();
            $data = $this->unapplycation->processUpdateUnapplicationItems($items, $user);
            $mensaje = $data['updated'] > 0 ? 'Aplicaciones desaplicadas correctamente.' : 'No se desaplicó ningún registro.';
            return $this->buildSuccessResponse($mensaje,  200);
        }, 'laravel', 'actualizarAplicaciones');
    }

    public function actualizarListaDocumentosAplicacion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $clientId   = $request->input('clienteId');
            $documents  = $request->input('documentos', []);
            $response   = $this->customer->updatePendingApplicationDocumentBalances($clientId, $documents);
            return $this->successResponse('Lista actualizada correctamente', $response, 200);
        }, 'laravel', 'actualizacionDocumentosAplicacion');
    }

    public function listaHistoricaDepositos(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters = $request->input('filters', []);
            $response = $this->historyDeposits->fetchListHistoricalDeposits($filters);
            return $this->successResponse('Datos obtenidos correctamente', $response, 200);
        }, 'laravel', 'listaHistoricaDepositos');
    }


    /**
     * nuevas funciones de cobros facturas
     */


    public function obtenerEstadoCajaUsuario(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'fecha'     => 'nullable|date',
                'pvId'      => 'nullable|integer',
                'pvCodigo'  => 'nullable|string'
            ]);
            $fecha = data_get($validate, 'fecha');
            $pvId  = data_get($validate, 'pvId');
            $pvCode = data_get($validate, 'pvCodigo');
            $user = $request->user() ?? GpUsuario::find(179);
            $caja = $this->invoiceCollect->getStatusBoxedUser($pvId, $pvCode, $fecha, $user);
            return $this->successResponse('Datos obtenidos correctamente', $caja, 200);
        }, 'laravel', 'obtenerEstadoCajaUsuario');
    }


    public function aperturarCajaUsuario(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'pvId' =>  'required|integer|exists:gp_punto_ventas,id',
            ]);
            $user = $request->user() ?? GpUsuario::find(179);
            $pvId = data_get($validate, 'pvId');
            $caja = $this->invoiceCollect->openCashBoxed($pvId, $user);
            return $this->successResponse('Apertura de Caja Realizado con exito', $caja, 200);
        }, 'laravel', 'abrirCajaUsuario');
    }


    public function imprimirPdfEstadoCuentaCliente(Request $request): Response
    {
        return $this->handleDocumentAction(function () use ($request): Response {
            $validate  = $request->validate([
                'id' => ['required', 'integer', 'exists:gp_clientes,id'],
            ]);
            $id        = data_get($validate, 'id');
            $response  = $this->invoiceCollect->generateClientAccountStatementPdf($id);
            return $this->buildFileResponse($response);
        }, 'laravel', 'imprimirPdfEstadoCuentaCliente');
    }

    public function imprimirExcelEstadoCuentaCliente(Request $request): Response
    {
        return $this->handleDocumentAction(function () use ($request): Response {
            $validate  = $request->validate([
                'id' => ['required', 'integer', 'exists:gp_clientes,id'],
            ]);
            $id        = data_get($validate, 'id');
            $response  = $this->invoiceCollect->generateClientAccountStatementExcel($id);
            return $this->buildFileResponse($response);
        }, 'laravel', 'imprimirExcelEstadoCuentaCliente');
    }



    public function obtenerFacturasEstadoCuentaCliente(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate  = $request->validate([
                'id' => ['required', 'integer', 'exists:gp_clientes,id'],
            ]);
            $id = data_get($validate, 'id');
            $response = $this->invoiceCollect->getInvoiceCollectionsByClient($id);
            return $this->successResponse('Información Obtenida Correctamente', $response);
        }, 'laravel', 'obtenerFacturasEstadoCuentaCliente');
    }

    public function obtenerListaDeDocumentosPendientesAplicacion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate  = $request->validate([
                'id' => ['required', 'integer', 'exists:gp_clientes,id'],
            ]);
            $id = data_get($validate, 'id');
            $response = $this->invoiceCollect->getUnappliedCollectionsByClient($id);
            return $this->successResponse('Información Obtenida Correctamente', $response);
        }, 'laravel', 'obtenerListaDeDocumentosPendientesAplicacion');
    }

    public function obtenerListaBancos(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $response = $this->invoiceCollect->getBanks();
            return $this->successResponse('Información Obtenida Correctamente', $response);
        }, 'laravel', 'obtenerListaBancos');
    }

    public function consultarExistenciaRetencion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate  = $request->validate([
                'id'            => ['required', 'integer', 'exists:gp_clientes,id'],
                'comprobante'   => ['required', 'string']
            ]);

            $id         = data_get($validate, 'id');
            $documento  = data_get($validate, 'comprobante');
            $exist      = $this->invoiceCollect->retentionReceiptExistsForClient($id, $documento);
            if ($exist) {
                return $this->buildExistResponse(false, 'Comprobante ya ingresado con anterioridad', 200);
            }
            return $this->buildExistResponse(true, 'Documento no existente, proceda', 200);
        }, 'laravel', 'consultarExistenciaRetencion');
    }

    public function consultarExistenciaDeposito(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'cliente_id'            => ['required', 'integer', 'exists:gp_clientes,id'],
                'comprobante'   => ['required', 'string'],
                'banco_id'      => ['required']
            ]);

            $id         = data_get($validate, 'cliente_id');
            $documento  = data_get($validate, 'comprobante');
            $bancoId    = data_get($validate, 'banco_id');
            $exists     = $this->invoiceCollect->depositReceiptExistsForClient($id, $bancoId, $documento);
            if ($exists) {
                return $this->buildExistResponse(false, 'Comprobante ya ingresado con anterioridad', 200);
            }
            return $this->buildExistResponse(true, 'Documento no existente, proceda', 200);
        }, 'laravel', 'consultarExistenciaDeposito');
    }


    public function consultarExistenciaCheque(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'cliente_id'   => ['required', 'integer', 'exists:gp_clientes,id'],
                'cheque'       => ['required', 'string'],
                'banco_id'     => ['required'],
                'cuenta'       => ['required', 'string'],
            ]);
            $cuenta = data_get($validate, 'cuenta');
            $cheque = data_get($validate, 'cheque');
            $exist = $this->invoiceCollect->checkReceiptExistsForClient($cuenta, $cheque);
            if ($exist) {
                return $this->buildExistResponse(false, 'Cheque ya ingresado con anterioridad', 200);
            }
            return $this->buildExistResponse(true, 'Cheque no existente, proceda', 200);
        }, 'laravel', 'consultarExistenciaCheque');
    }



    public function listaCobrosPorCliente(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $filtros    = $request->input('filters', []);
            $lista      = $this->invoiceCollect->fetchListCollection($filtros);
            return $this->successResponse('Información Obtenida Correctamente', $lista);
        }, 'laravel', 'listaCobrosPorCliente');
    }

    public function imprirCobroPdf(Request $request): Response
    {
        return $this->handleDocumentAction(function () use ($request): Response {
            $validate = $request->validate([
                'cobro_id'   => ['required', 'integer', 'exists:gp_cobros,id'],
            ]);
            $cobroId         = data_get($validate, 'cobro_id');
            $usuario = $request->user() ?? GpUsuario::find(179);
            $resultado  = $this->invoiceCollect->printCollectionPdf($cobroId, $usuario);
            return $this->buildFileResponse($resultado);
        }, 'laravel', 'imprirCobroPdf');
    }

    public function registrarCobroV1(RegisterCollectionRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validated();
            $user = $request->user() ?? GpUsuario::find(179);
            $cobro = $this->collectionRegistrationService->registerCollection($validate, $user);
            $mensaje = "Cobro {$cobro->numero_recibo} enviado a procesar.!";
            return $this->buildSuccessResponse($mensaje,  200);
        }, 'laravel', 'registrarCobro');
    }




    /** api para enviar un cobro en especifico a procesar */
    public function enviarCobroGp(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'cobro_id'   => ['required', 'integer', 'exists:gp_cobros,id'],
            ]);
            $cobroId         = data_get($validate, 'cobro_id');
            $data = $this->sendCollectionService->sendCollectionToGp($cobroId);
            return $this->successResponse('Cobro Registrado', $data);
        }, 'laravel', 'enviarCobroGp');
    }
}
