<?php

namespace App\Http\Controllers\Movil\Inventario\GuiasRemision;

use App\Http\Controllers\Controller;
use App\Models\despachos\GpGuiaRemision;
use App\Models\Reservas\GpGuiaRemisionReserva;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteMediaServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteQrServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class GuiaRemisionArchivoController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    private const LOG_CHANNEL = 'stack';

    public function __construct(
        protected readonly DeliveryNoteMediaServiceInterface $media,
        protected readonly DeliveryNoteQrServiceInterface $qr
    ) {}

    public function resolverQr(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validated = $request->validate(['codigo' => ['required', 'string', 'max:250']]);
            $target = $this->qr->decode($validated['codigo']);
            // Reutiliza la misma autorización que la consulta y carga de firmas.
            $files = $this->media->getGuideFiles($target['tipo'], $target['guia_id'], (int) $request->user()->id);
            $guide = $target['tipo'] === 'reserva'
                ? GpGuiaRemisionReserva::findOrFail($target['guia_id'])
                : GpGuiaRemision::where('tipo', $target['tipo'])->findOrFail($target['guia_id']);
            $expected = $target['tipo'] === 'despacho' ? 'enviada' : 'recibida';
            if ($guide->estado !== $expected) {
                throw ValidationException::withMessages([
                    'guia' => 'El estado de la guía no permite firmar.',
                ]);
            }
            return $this->successResponse('Guía lista para firmar.', $target + [
                'accion' => 'firmar_guia',
                'numero_guia' => $target['tipo'] === 'reserva' ? $guide->numero_guia : $guide->GUI_REM_GUIA_REMISION,
                'estado' => $guide->estado,
                'tiene_firma' => count($files['firmas']) > 0,
                'firma_endpoint' => url('api/movil/inventario/guias-remision/' . $target['tipo'] . '/' . $target['guia_id'] . '/firma'),
                'archivos' => $files,
            ], 200);
        }, self::LOG_CHANNEL, 'GuiaRemisionArchivoController@resolverQr');
    }

    public function index(Request $request, string $tipo, int $guia): JsonResponse
    {
        return $this->handleApiAction(function () use ($request, $tipo, $guia): JsonResponse {
            $user = $request->user();
            $data = $this->media->getGuideFiles($tipo, $guia, (int) $user->id);
            return $this->successResponse('Archivos de la guía obtenidos.', $data, 200);
        }, self::LOG_CHANNEL, 'GuiaRemisionArchivoController@index');
    }

    public function firma(Request $request, string $tipo, int $guia): JsonResponse
    {
        return $this->handleApiAction(function () use ($request, $tipo, $guia): JsonResponse {
            $validated = $request->validate([
                'firma' => ['required', 'file', 'image', 'mimetypes:image/png', 'max:2048'],
                'nombre_firmante' => ['required', 'string', 'max:200'],
                'identificacion_firmante' => ['required', 'string', 'max:30'],
                'latitud' => ['required', 'numeric', 'between:-90,90'],
                'longitud' => ['required', 'numeric', 'between:-180,180'],
                'precision_ubicacion' => ['required', 'numeric', 'min:0'],
                'fecha_firma' => ['required', 'date'],
            ]);
            $user = $request->user();
            $data = $this->media->storeSignature($tipo, $guia, $request->file('firma'),
                (int) $user->id, $validated['nombre_firmante'], $validated['identificacion_firmante'], [
                    'latitud' => (float) $validated['latitud'],
                    'longitud' => (float) $validated['longitud'],
                    'precision_ubicacion' => (float) $validated['precision_ubicacion'],
                    'fecha_firma' => \Illuminate\Support\Carbon::parse($validated['fecha_firma'])->utc()->toIso8601String(),
                ]);
            return $this->successResponse('Firma guardada.', $data, 201);
        }, self::LOG_CHANNEL, 'GuiaRemisionArchivoController@firma');
    }

    public function documentos(Request $request, string $tipo, int $guia): JsonResponse
    {
        return $this->handleApiAction(function () use ($request, $tipo, $guia): JsonResponse {
            $request->validate([
                'documentos' => ['required', 'array', 'min:1', 'max:10'],
                'documentos.*' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:10240'],
            ]);
            $user = $request->user();
            $data = $this->media->storeDocuments($tipo, $guia, $request->file('documentos'), (int) $user->id);
            return $this->successResponse('Documentos guardados.', $data, 201);
        }, self::LOG_CHANNEL, 'GuiaRemisionArchivoController@documentos');
    }

    public function show(Request $request, string $tipo, int $guia, int $archivo): Response
    {
        return $this->handleDocumentAction(function () use ($request, $tipo, $guia, $archivo): Response {
            $user = $request->user();
            return $this->media->downloadGuideFile($tipo, $guia, $archivo, (int) $user->id);
        }, self::LOG_CHANNEL, 'GuiaRemisionArchivoController@show');
    }
}
