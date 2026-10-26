<?php

namespace App\Http\Controllers\Ventas\Clientes;

use App\Http\Controllers\Controller;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Gestion\Sucursales\Contracts\ClientBranchServiceInterface;
use App\Services\Ventas\Clientes\Contracts\ClientCreatorServiceInterface;
use App\Services\Ventas\Clientes\Contracts\ClientQueryServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    private const LOG_CHANNEL = 'stack';
    public function __construct(
        protected readonly ClientQueryServiceInterface $clientService,
        protected readonly ClientCreatorServiceInterface $clientCreatorService,
        protected readonly ClientBranchServiceInterface $branchService
    ) {}



    public function obtenerListaTipoIdentificacion(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            return $this->successResponse('Lista Obtenida', $this->clientService->getListTypeDni());
        }, self::LOG_CHANNEL, 'obtenerListaTipoIdentificacion');
    }

    public function buscarClientes(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $rq         = $request->all();
            $search     = data_get($rq, 'search', null);
            $tipo       = data_get($rq, 'tipo', null);
            $clientes   = $this->clientService->getAllClients($search, $tipo);
            return $this->successResponse('Clientes obtenidos correctamente', $clientes, 200);
        }, self::LOG_CHANNEL, 'buscarClientes');
    }

    public function obtenerCupoDisponibleCliente(string $ruc): JsonResponse
    {
        return $this->handleApiAction(function () use ($ruc): JsonResponse {
            $response = $this->clientService->getAvailabeCreditCustomer($ruc);
            return $this->successResponse('Cupo disponible obtenido correctamente', $response, 200);
        }, 'laravel', 'obtenerCupoDisponibleCliente');
    }

    public function obtenerSucursalesCliente(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $validated = $request->validate([
                'ruc'   => 'required|string',
                'tipo'  => 'required|string'
            ]);
            $ruc    = data_get($validated, 'ruc');
            $tipo   = data_get($validated, 'tipo');
            $resp   = $this->clientService->getCustomerBranches($ruc, $tipo);
            return $this->successResponse('Sucursales obtenidas correctamente', $resp, 200);
        }, 'laravel', 'obtenerSucursalesCliente');
    }

    public function obtenerListaSegmentos(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $resp = $this->clientService->getAllSegments();
            return $this->successResponse('Segmentos obtenidos correctamente', $resp, 200);
        }, self::LOG_CHANNEL, 'obtenerListaSegmentos');
    }

    public function obtenerListaSubSegmentos(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $coAtrPadre = $request->get('segmento', null);
            $resp       = $this->clientService->getSubSegmentBySegment($coAtrPadre);
            return $this->successResponse('Segmentos obtenidos correctamente', $resp, 200);
        }, self::LOG_CHANNEL, 'obtenerListaSubSegmentos');
    }

    public function validarExistenciIdentificacion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $documento  = $request->input('documento');
            $existe = $this->clientService->validateHasExistenceDocument($documento);
            if ($existe) {
                return $this->buildExistResponse(false, 'El numero de identificación ya se encuentra registrado con anterioridad', 200);
            }
            return $this->buildExistResponse(true, 'El registro no existe, continue...', 200);
        }, self::LOG_CHANNEL, 'validarExistenciIdentificacion');
    }

    public function valdiarExitenciaIdentificacionProspecto(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $documento  = $request->input('documento');
            $existe = $this->clientService->validateHasExistenceProspect($documento);
            if ($existe) {
                return $this->buildExistResponse(false, 'El numero de identificación ya se encuentra registrado con anterioridad', 200);
            }
            return $this->buildExistResponse(true, 'El registro no existe, continue...', 200);
        }, self::LOG_CHANNEL, 'valdiarExitenciaIdentificacionProspecto');
    }


    public function obtenerListaGrupoClientes(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $lista = $this->clientService->getListGrupCustomers();
            return $this->successResponse('Información Obtenida correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'obtenerListaGrupoClientes');
    }

    public function guardarCliente(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->input('payload', []);
            $usuario = $request->user() ?? GpUsuario::find(179);
            $cliente = $this->clientCreatorService->createClient($data, $usuario);
            return $this->successResponse("Cliente creado con exito.!", $cliente);
        }, self::LOG_CHANNEL, 'guardarCliente');
    }


    public function crearNuevaDireccion(Request $request){
        return $this->handleDocumentAction(function() use($request){
        $data = $request->input('form', []);
        $user = $request->user();
        $sucursal = $this->branchService->createClientBranch($data, $user);
        return $this->successResponse('Dirección creada con exito.!', $sucursal);
        }, self::LOG_CHANNEL, 'crearNuevaDireccion');
    }
}
