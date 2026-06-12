<?php

namespace App\Http\Controllers\Movil;

use App\Http\Controllers\Controller;
use App\Models\GpUsuario;
use App\Services\Movil\Gestion\Contracts\SteamTrapServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class SteamTrapController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly SteamTrapServiceInterface $service) {}

    public function generarDimensionamientoPdf(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data = $request->validate([
                'customer_id'               => ['required', 'integer'],
                'customer_ruc'              => ['required', 'string'],

                'pvId'                      => ['required', 'integer'],
                'pvDescription'             => ['required', 'string'],
                'pvCode'                    => ['required', 'string'],

                'process'                   => ['required', 'string'],
                'trapType'                  => ['required', 'string'],
                'calculationMethod'         => ['required', 'string'],

                'workingPressurePsig'       => ['required', 'numeric', 'gt:0'],
                'returnPressurePsig'        => ['required', 'numeric', 'gte:0'],
                'pressureDifferentialPsig'  => ['required', 'numeric', 'gt:0'],

                'fluid'                     => ['nullable', 'string'],
                'volumeM3'                  => ['nullable', 'numeric'],
                'densityKgM3'               => ['nullable', 'numeric'],
                'cpKjKgC'                   => ['nullable', 'numeric'],
                'initialTemperatureC'       => ['nullable', 'numeric'],
                'finalTemperatureC'         => ['nullable', 'numeric'],
                'heatingTimeMin'            => ['nullable', 'numeric'],
                'requiredHeatKj'            => ['nullable', 'numeric'],
                'hfgKjKg'                   => ['nullable', 'numeric'],

                'knownLoadKgH'              => ['nullable', 'numeric'],
                'boilerBhp'                 => ['nullable', 'numeric'],

                'estimatedLoadKg'           => ['required', 'numeric', 'gt:0'],
                'safetyFactor'              => ['required', 'numeric', 'gte:1'],
                'loadWithSafetyFactorKg'    => ['required', 'numeric', 'gt:0'],

                'spParams.workingPressurePsig'  => ['required', 'numeric', 'gt:0'],
                'spParams.returnPressurePsig'   => ['required', 'numeric', 'gte:0'],
                'spParams.estimatedLoadKg'      => ['required', 'numeric', 'gt:0'],
                'spParams.safetyFactor'         => ['required', 'numeric', 'gte:1'],
            ]);
            $user = $request->user();
            $pdfUrl = $this->service->generateSizingPdf($data, $user);
            return $this->successResponse('Pdf generado Correctamente', [
                'pdfUrl' => $pdfUrl,
            ], 200);
        }, 'laravel', 'SteamTrapController.generateSizingPdf');
    }


    public function generarCotizacionBorrador(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data = $request->validate([
                'customer_id'               => ['required', 'integer'],
                'customer_ruc'              => ['required', 'string'],

                'pvId'                      => ['required', 'integer'],
                'pvDescription'             => ['required', 'string'],
                'pvCode'                    => ['required', 'string'],

                'process'                   => ['required', 'string'],
                'trapType'                  => ['required', 'string'],
                'calculationMethod'         => ['required', 'string'],

                'workingPressurePsig'       => ['required', 'numeric', 'gt:0'],
                'returnPressurePsig'        => ['required', 'numeric', 'gte:0'],
                'pressureDifferentialPsig'  => ['required', 'numeric', 'gt:0'],

                'fluid'                     => ['nullable', 'string'],
                'volumeM3'                  => ['nullable', 'numeric'],
                'densityKgM3'               => ['nullable', 'numeric'],
                'cpKjKgC'                   => ['nullable', 'numeric'],
                'initialTemperatureC'       => ['nullable', 'numeric'],
                'finalTemperatureC'         => ['nullable', 'numeric'],
                'heatingTimeMin'            => ['nullable', 'numeric'],
                'requiredHeatKj'            => ['nullable', 'numeric'],
                'hfgKjKg'                   => ['nullable', 'numeric'],

                'knownLoadKgH'              => ['nullable', 'numeric'],
                'boilerBhp'                 => ['nullable', 'numeric'],

                'estimatedLoadKg'           => ['required', 'numeric', 'gt:0'],
                'safetyFactor'              => ['required', 'numeric', 'gte:1'],
                'loadWithSafetyFactorKg'    => ['required', 'numeric', 'gt:0'],

                'spParams.workingPressurePsig'  => ['required', 'numeric', 'gt:0'],
                'spParams.returnPressurePsig'   => ['required', 'numeric', 'gte:0'],
                'spParams.estimatedLoadKg'      => ['required', 'numeric', 'gt:0'],
                'spParams.safetyFactor'         => ['required', 'numeric', 'gte:1'],
            ]);
            // $user = $request->user();
            $user = GpUsuario::find(179);
            $cotizacion = $this->service->generateQuoteDraft($data, $user);
            $msg = "Cotización generada {$cotizacion->cotizacion}";
            return $this->buildSuccessResponse($msg, 200);
        }, 'laravel', 'generarCotizacionBorrador');
    }
}
