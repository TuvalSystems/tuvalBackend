<?php

namespace App\Http\Controllers\Devoluciones;

use App\Enums\Ventas\OrderStatus;
use App\Models\Ventas\Pedidos\GpPedido;
use Illuminate\Http\Request;
use App\Models\Inventario\Despachos\GpOrdenDespacho;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\Devoluciones\GpDevoluciones;
use App\Notifications\User\UserNotification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Http\Requests\Devoluciones\ValidateReceivedDevolutionsRequest;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Comercial\CommercialVisibilityService;
use App\Traits\ExecuteApiTransactions;
use Carbon\Carbon;

class DevolucionesController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(
        protected readonly  EntryServiceInterface $entryService,
        protected readonly  DevolucionServiceInterface $devolucionService,
        protected readonly CommercialVisibilityService $commercial
    ) {}


    public function pedidoscliente(Request $request, int $cliente_id, string $puntodeventa_id)
    {
        $user = $request->user() ?? GpUsuario::find(137);

        $pedidosQuery = GpPedido::query()->with('direccionEntrega')
            ->where('PTO_VTA_ID', $puntodeventa_id)
            ->where('cliente_id', $cliente_id)
            ->where(function ($query) {
                $query->where('estado', OrderStatus::FACTURADO)
                    ->orWhere('estado', OrderStatus::ANULADA);
            });
        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $puntodeventa_id)->first();


        $this->commercial->applyLimitsVisibiliteList($pedidosQuery, $user, $puntoVenta->id, 'pedidos', 'gp_pedidos.user_id');

        $pedidos = $pedidosQuery
            ->orderByDesc('gp_pedidos.created_at')
            ->limit(10000)
            ->get();

        $ordenesDespachoPedidos = GpOrdenDespacho::query()
            ->whereIn('pedido_id', $pedidos->pluck('id'))
            ->whereIn('estado', ['liberada', 'despachada'])
            ->pluck('pedido_id')
            ->all();

        $pedidos->each(function ($pedido) use ($ordenesDespachoPedidos) {
            $pedido->orden_despacho = in_array(
                $pedido->id,
                $ordenesDespachoPedidos,
                true
            );
        });

        $devolucionesQuery = GpDevoluciones::query()
            ->with([
                'cliente',
                'cliente_volver_a_facturar',
                'sucursal_volver_a_facturar',
            ])
            ->where('gp_devoluciones.cliente_id', $cliente_id)
            ->whereHas('pedido', function ($query) use ($puntodeventa_id) {
                $query->where(
                    'gp_pedidos.PTO_VTA_ID',
                    $puntodeventa_id
                );
            });

        $this->commercial->applyLimitsVisibiliteList($devolucionesQuery, $user, $puntoVenta->id, 'devoluciones',  'gp_devoluciones.user_id');

        $devoluciones = $devolucionesQuery
            ->orderByDesc('gp_devoluciones.created_at')
            ->get();

        $ordenesDespachoDevoluciones = GpOrdenDespacho::query()
            ->whereIn(
                'pedido_id',
                $devoluciones->pluck('pedido_id')
            )
            ->whereIn('estado', ['liberada', 'despachada'])
            ->pluck('pedido_id')
            ->all();

        $devoluciones->each(
            function ($devolucion) use ($ordenesDespachoDevoluciones) {
                $devolucion->orden_despacho = in_array(
                    $devolucion->pedido_id,
                    $ordenesDespachoDevoluciones,
                    true
                );
            }
        );


        return response([
            'success' => true,
            'pedidos' => $pedidos,
            'devoluciones' => $devoluciones,
        ]);
    }

    public function detallepedidodevolucion(Request $request)
    {
        try {
            $data = $request->all();
            $info = $this->devolucionService->getDetailOrderReturnById($data);
            return response([
                'success' => true,
                'detalle' => $info['detalle'],
                'motivos' => $info['motivos'],
                'clientes' => $info['clientes'],
                'devolucion_anterior' => $info['devolucion_anterior'],
                'items_devueltos' => $info['items_devueltos'],
            ]);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }




    public function enviarDevolucion(Request $request): JsonResponse
    {
        $data = $request->all();
        $gpUser = $request->user();
        try {
            $newDev     = $this->devolucionService->handleDevolutionTransaction($data, $gpUser);
            $statusDev  = $newDev->estado === 'solicitado';
            $tipoDev    = $newDev->tipo_devolucion === 'total';
            $devCode    = is_object($newDev) ? $newDev->devolucion : '';
            if (isset($newDev->motivo_no_anulada) && $newDev->estado = 'no_procesado') {
                $gpUser->notify(new UserNotification('Devolucion No Procesada', $newDev->warning_message));
                return $this->buildSuccessResponse(
                    "ATENCIÓN: " . $newDev->motivo_no_anulada,
                    200
                );
            }
            if ($statusDev && $tipoDev) {
                // Caso: Tiene pagos registrados
                if (!$newDev->permite_anulacion) {
                    return $this->buildSuccessResponse(
                        "La devolución {$devCode} ha sido solicitada. No se podrá anular la factura ya que tiene pagos registrados. La devolución será procesada normalmente.",
                        200
                    );
                }

                if (!$newDev->anulacion_habilitada) {
                    return $this->buildSuccessResponse(
                        "La devolución {$devCode} ha sido solicitada, pero no se procesará porque excede el límite de días permitido para anulaciones. La devolución será procesada normalmente.",
                        200
                    );
                }
            }
            return $this->buildSuccessResponse("La devolución  {$devCode} ha sido solicitada. ", 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $th) {
            $logId = isset($newDev) ? $newDev->id : 'No creado';
            $msgError = isset($newDev) ? $newDev->motivo_no_anulada : $th->getMessage();
            Log::channel('devolucion')->info("Error al Procesar la devolución: {$th->getMessage()} - Devolución ID: {$logId}");
            $gpUser->notify(new UserNotification('Error al Procesar la devolucion', $msgError));
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function detalledevolucion(Request $request)
    {
        $data = $request->all();
        return $this->devolucionService->getReturnsDetailDevolucion($data);
    }



    public function listaDevolucionesPorRecibir(Request $request): JsonResponse
    {
        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'desc');
        $ptoVtaId = $request->input('ptoVtaId', null);

        try {
            $data = $this->devolucionService->fetchReturnsToReceived($perPage, $search, $sortBy, $sortOrder, $ptoVtaId);
            return $this->successResponse('Lista de devoluciones por recibir obtenida correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error al obtener la lista de devoluciones por recibir: ' . $e->getMessage(), [], 500);
        }
    }

    public function detalleDevolucionPorRecibir(Request $request): JsonResponse
    {
        $devolucionId = $request->input('id');
        try {
            $data = $this->devolucionService->getReturnDetailToReceiveById($devolucionId);
            return $this->successResponse('Detalle de devolución por recibir obtenido correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error al obtener el detalle de la devolución por recibir: ' . $e->getMessage(), [], 500);
        }
    }


    public function imprimirOrdenDeAlmacenamiento(Request $request)
    {
        $data = $request->all();
        try {
            $response = $this->devolucionService->generatePdfOrderStorageDevolution($data);
            return $this->buildFileResponse($response);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function recibirDevolucion(ValidateReceivedDevolutionsRequest $request)
    {
        try {
            $data = $request->validated();
            $user = $request->user();
            $resp = $this->devolucionService->receivedDevolutions(
                data_get($data, 'header'),
                data_get($data, 'details'),
                $user
            );
            return $this->successResponse('Devoluciones recibidas correctamente', $resp, 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $th) {
            return $this->buildErrorResponse('Error al recibir la devolución ' . $th->getMessage(), 500);
        }
    }


    public function imprimirdevolucion(int $id)
    {
        $dev = GpDevoluciones::findOrFail($id);
        $order = GpPedido::where('id', $dev->pedido_id)->first();
        $anioDev = (int) Carbon::parse($order->fecha_factura)->format('Y');
        $devolucion = ($anioDev <= 2025) ? $this->devolucionService->printReturnOld($id) : $this->devolucionService->printReturn($id);
        $pdf = PDF::loadView('devolucionPDF', $devolucion);
        return $pdf->stream('nota_de_credito.pdf');
    }


    public function rechazarDevolucion(Request $request)
    {
        $data = $request->all();
        try {
            $this->devolucionService->dennyDevolucion(data_get($data, 'id'), $request->user());
            return $this->buildSuccessResponse("Devolución rechazada correctamente", 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse('Error al rechazar la devolución ' . $th->getMessage(), 500);
        }
    }



    /**
     * PRUEBAS PARA GENERACION DE NOTA DE CREDITO --web
     */

    public function generarNotaCreditoDevolucionesFallidas(int $id)
    {
        return $this->handleApiAction(function () use ($id) {
            $devolucion = $this->devolucionService->generarNotaCreditoDevolucionesFallidas($id);
            return $this->successResponse('Nota de credito generada correctamente', $devolucion, 200);
        }, 'devolucion', 'generarNotaCreditoDevolucionesFallidas');
    }

    public function generarAjusteDevolucionesFallidas(int $id)
    {
        return $this->devolucionService->generarAjusteDevolucionesVentaFallidas($id);
    }

    public function restaurarNotaCreditoDevolucion(int $id)
    {
        try {
            $dev = $this->devolucionService->responseCreditNoteDevolution($id);
            return $this->successResponse('Devolución restaurada correctamente', $dev, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al restaurar la devolución: ' . $e->getMessage(), 500);
        }
    }
}
