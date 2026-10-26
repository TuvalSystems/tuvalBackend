<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Integraciones\Soap\WebserviceController;
use App\Models\Cobranzas\Aplicaciones\GpCobrosAplicacionFactura;
use App\Models\Cobranzas\Aplicaciones\GpCobrosAplicacionMonto;
use App\Models\Cobranzas\Aplicaciones\GpCobrosTarjetaFactura;
use App\Models\Cobranzas\Aplicaciones\GpCobrosTarjetaMonto;
use App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaCaja;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\Cobranzas\Cobros\GpCobro;
use App\Models\Cobranzas\Retenciones\GpCobrosRetenciones;
use App\Models\Cobranzas\Efectivo\GpCobrosEfectivoMonto;
use App\Models\Cobranzas\Efectivo\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\Depositos\GpCobrosDeposito;
use App\Models\Cobranzas\Depositos\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\Cheques\GpCobrosCheques;
use App\Models\Cobranzas\Cheques\GpCobrosChequesMonto;
use App\Models\Cobranzas\Cheques\GpCobrosChequesFacturas;
use App\Models\Ventas\Clientes\GpCliente;
use App\Http\Requests\Cobranzas\CobrosFacturas\RegisterCollectionRequest;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Notifications\User\UserNotification;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionGpSenderServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Cobranzas\Contracts\PaymentReceiptLookupServiceInterface;

class CobrosController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        protected readonly PaymentReceiptLookupServiceInterface $paymentReceiptLookupService,
        protected readonly InvoiceCollectionServiceInterface $invoiceCollect,
        protected readonly CollectionRegistrationServiceInterface $collectionRegistrationService,
        protected readonly CollectionGpSenderServiceInterface $sendCollectionService
    ) {}

    public function consultarCobroPorComprobante(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'comprobante' => ['required', 'string', 'max:100'],
            ]);
            $payments = $this->paymentReceiptLookupService->getPaymentsByDepositReference($validated['comprobante']);
            return $this->successResponse('Consulta de cobros realizada correctamente', $payments, 200);
        }, 'cobranzas', 'consultarCobroPorComprobante');
    }

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

    public function obtenerListaBancos(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $response = $this->invoiceCollect->getBanks();
            return $this->successResponse('Información Obtenida Correctamente', $response);
        }, 'cobranzas', 'obtenerListaBancos');
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
        }, 'cobranzas', 'consultarExistenciaRetencion');
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
        }, 'cobranzas', 'consultarExistenciaDeposito');
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
        }, 'cobranzas', 'consultarExistenciaCheque');
    }

    public function listaCobrosPorCliente(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $filtros    = $request->input('filters', []);
            $lista      = $this->invoiceCollect->fetchListCollection($filtros);
            return $this->successResponse('Información Obtenida Correctamente', $lista);
        }, 'cobranzas', 'listaCobrosPorCliente');
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
        }, 'cobranzas', 'imprirCobroPdf');
    }

    public function registrarCobroV1(RegisterCollectionRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validated();
            $user = $request->user() ?? GpUsuario::find(179);
            $cobro = $this->collectionRegistrationService->registerCollection($validate, $user);
            $mensaje = "Cobro {$cobro->numero_recibo} enviado a procesar.!";
            return $this->buildSuccessResponse($mensaje,  200);
        }, 'cobranzas', 'registrarCobro');
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
        }, 'cobranzas', 'enviarCobroGp');
    }

    /** api para enviar lote de cobros al job en estado iniciado o recibido  */
    public function enviarCobrosLote(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $this->collectionRegistrationService->reSendCollectionPendings();
            return $this->buildSuccessResponse("Cobros enviados a procesar", 200);
        }, 'cobranzas', 'enviarCobrosLote');
    }
}
