<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Services\Integraciones\Soap\LegacyGpSoapService;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;

class EstadoCuentaController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        protected readonly InvoiceCollectionServiceInterface $invoiceCollect
    ) {}

    public function documentosvencidoscliente(Request $request): HttpResponse|Application|ResponseFactory
    {
        $data = $request->validate([
            'CLI_RUC' => 'required',
        ]);

        $webservices = new LegacyGpSoapService();
        $documentosVencidos = $webservices->documentosvencidoscliente($data['CLI_RUC']);
        return response([
            'success' => true,
            'facturas' => $documentosVencidos,
        ]);
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
        }, 'cobranzas', 'imprimirPdfEstadoCuentaCliente');
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
        }, 'cobranzas', 'imprimirExcelEstadoCuentaCliente');
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
        }, 'cobranzas', 'obtenerFacturasEstadoCuentaCliente');
    }
}
