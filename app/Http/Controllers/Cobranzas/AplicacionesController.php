<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use App\Services\Cobranzas\Contracts\CollectionUnapplicationServiceInterface;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AplicacionesController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        protected readonly CustomerPaymentApplicationServiceInterface $customer,
        protected readonly CollectionUnapplicationServiceInterface $unapplycation,
        protected readonly InvoiceCollectionServiceInterface $invoiceCollect
    ) {}

    /** funciones para la validacion de pagos de facturas  */
    public function obtenerInformacionPagosFacturas(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $payload    = $request->input('data', []);
            $user       = $request->user() ?? GpUsuario::find(179);
            $info       = $this->customer->validateIdentifyPaymentInvoices($payload, $user);
            return $this->successResponse('Información de Pagos Obtenido!', $info, 200);
        }, 'cobranzas', 'obtenerInformacionPagosFacturas');
    }

    /** function para listar los cobros que pueden ser desaplicados  */
    public function listaCobrosAplicados(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters  = $request->input('filters', []);
            $lista     = $this->unapplycation->fetchAppliedCollections($filters);
            return $this->successResponse('Lista obtenida', $lista, 200);
        }, 'cobranzas', 'listaCobrosPorDesaplicar');
    }

    public function detalleCobrosAplicados(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $ruc = $request->input('ruc');
            $factura = $request->input('factura');
            $user = $request->user();
            $data = $this->unapplycation->getAllDetailCollection($ruc, $factura, $user);
            return $this->successResponse('Obtenido con Éxito', $data, 200);
        }, 'cobranzas', 'detaleCobrosAplicados');
    }

    public function procesarDesaplicaciones(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $items = $request->input('payload', []);
            $user = $request->user();
            $data = $this->unapplycation->processUpdateUnapplicationItems($items, $user);
            $mensaje = $data['updated'] > 0 ? 'Aplicaciones desaplicadas correctamente.' : 'No se desaplicó ningún registro.';
            return $this->buildSuccessResponse($mensaje,  200);
        }, 'cobranzas', 'actualizarAplicaciones');
    }

    public function actualizarListaDocumentosAplicacion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $clientId   = $request->input('clienteId');
            $documents  = $request->input('documentos', []);
            $response   = $this->customer->updatePendingApplicationDocumentBalances($clientId, $documents);
            return $this->successResponse('Lista actualizada correctamente', $response, 200);
        }, 'cobranzas', 'actualizacionDocumentosAplicacion');
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
        }, 'cobranzas', 'obtenerListaDeDocumentosPendientesAplicacion');
    }
}
