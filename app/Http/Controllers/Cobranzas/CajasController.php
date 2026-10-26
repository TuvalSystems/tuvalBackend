<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\GpCierreCaja;
use App\Models\GpCobrosAplicacionFactura;
use App\Models\GpCobrosAplicacionMonto;
use App\Models\GpCobrosTarjetaMonto;
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
use App\Models\Cobranzas\GpCobrosChequesFacturas;
use App\Models\GpRelacionUsuarioCaja;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Models\GpCaja;
use App\Services\Cobranzas\CierreCajas\Contracts\CashRegisterClosureServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CajasController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        protected readonly CollectionServiceInterface $collectionService,
        protected readonly InvoiceCollectionServiceInterface $invoiceCollect,
        protected readonly CashRegisterClosureServiceInterface $cashClosureService
    ) {}

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

    public function listaPvCajasUsuario(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $user = $request->user();
            $response = $this->collectionService->listPointSalesByUSer($user);
            return $this->successResponse('Lista Obtenida', $response, 200);
        }, 'cobranzas', 'listaPvCajasUsuario');
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
        }, 'cobranzas', 'listarCajasDisponiblesPorUsuario');
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
        }, 'cobranzas', 'consultarCierreCajaUsuario');
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
        }, 'cobranzas', 'imprimirPdfConsultaCierreCaja');
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
        }, 'cobranzas', 'imprimirPdfCierreCaja');
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
        }, 'cobranzas', 'obtenerEstadoCajaUsuario');
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
        }, 'cobranzas', 'abrirCajaUsuario');
    }

    /** funciones para cierre de caja */
    public function cerrarCajaV1(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'pvId'      => ['required', 'integer', 'exists:gp_punto_ventas,id'],
                'pvCodigo'  => ['required', 'string'],
                'caja_id'   => ['required', 'integer', 'exists:gp_cajas,id'],
            ]);

            $pvId   = data_get($validate, 'pvId');
            $pvCode = data_get($validate, 'pvCodigo');
            $boxId  = data_get($validate, 'caja_id');
            $user   = $request->user() ?? GpUsuario::find(179);
            $this->cashClosureService->cashCloseBox($boxId, $pvId, $pvCode, $user);
            return $this->buildSuccessResponse("Caja Cerrada exitosamente", 200);
        }, 'cobranzas', 'cerrarCajaV1');
    }

    public function resumenCerrarCaja(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'pvId'      => ['required', 'integer', 'exists:gp_punto_ventas,id'],
                'pvCodigo'  => ['required', 'string'],
                'caja_id'   => ['required', 'integer', 'exists:gp_cajas,id'],
                'fecha'     => ['required', 'string']
            ]);

            $pvId   = data_get($validate, 'pvId');
            $pvCode = data_get($validate, 'pvCodigo');
            $boxId  = data_get($validate, 'caja_id');
            $fecha  = data_get($validate, 'fecha');
            $user   = $request->user() ?? GpUsuario::find(179);
            $response =  $this->cashClosureService->getCashBoxClosureSummary($boxId, $pvId, $pvCode, $fecha, $user);
            return $this->successResponse("Resumen de cierre de caja obtenido exitosamente", $response, 200);
        }, 'cobranzas', 'resumenCerrarCaja');
    }
}
