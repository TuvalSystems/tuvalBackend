<?php

namespace App\Services\Inventario\Bodegas;

use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteMediaServiceInterface;

use App\Models\despachos\GpGuiaRemision;
use App\Models\Reservas\GpGuiaRemisionReserva;
use App\Models\GpUsuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DeliveryNoteMediaService implements DeliveryNoteMediaServiceInterface
{
    public function getGuideFiles(string $type, int $guideId, int $userId): array
    {
        $guide = $this->resolve($type, $guideId, $userId);
        return $this->files($guide);
    }

    public function storeSignature(string $type, int $guideId, UploadedFile $signature,
        int $userId, ?string $signer = null, ?string $identification = null, array $location = []): array
    {
        $guide = $this->resolve($type, $guideId, $userId);
        return $this->store($guide, [$signature], 'firmas', $userId, $signer, $identification, $location);
    }

    public function storeDocuments(string $type, int $guideId, array $documents, int $userId): array
    {
        $guide = $this->resolve($type, $guideId, $userId);
        return $this->store($guide, $documents, 'documentos', $userId);
    }

    public function downloadGuideFile(string $type, int $guideId, int $mediaId, int $userId): StreamedResponse
    {
        $guide = $this->resolve($type, $guideId, $userId);
        return $this->download($guide, $mediaId);
    }

    public function getConsultationFiles(string $type, int $guideId, int $userId): array
    {
        return $this->files($this->resolve($type, $guideId, $userId, true));
    }

    public function downloadConsultationFile(string $type, int $guideId, int $mediaId, int $userId): StreamedResponse
    {
        return $this->download($this->resolve($type, $guideId, $userId, true), $mediaId);
    }

    protected function resolve(string $type, int $id, int $userId, bool $consultation = false): GpGuiaRemision|GpGuiaRemisionReserva
    {
        if (!in_array($type, ['reserva', 'despacho', 'reposicion', 'transferencia'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de guía inválido.']);
        }
        $guide = $type === 'reserva'
            ? GpGuiaRemisionReserva::findOrFail($id)
            : GpGuiaRemision::where('tipo', $type)->findOrFail($id);

        $allowed = DB::table('gp_bodegas as b')
            ->join('gp_relacion_punto_venta_bodegas as r', 'r.PTO_VTA_BOD_BODEGA', '=', 'b.BOD_BODEGA')
            ->join('gp_punto_ventas as pv', 'pv.PTO_VTA_ID', '=', 'r.PTO_VTA_BOD_PUNTO_VENTA')
            ->join('gp_relacion_usuario_punto_ventas as u', 'u.pv_id', '=', 'pv.id')
            ->where('u.user_id', $userId)
            ->whereIn('b.id', $consultation
                ? array_filter([$guide->bodega_origen_id, $guide->bodega_destino_id])
                : [$type === 'despacho' ? $guide->bodega_origen_id : $guide->bodega_destino_id])->exists();
        if (!$allowed) {
            throw new AccessDeniedHttpException('No tiene acceso a esta guía.');
        }

        return $guide;
    }

    protected function files(GpGuiaRemision|GpGuiaRemisionReserva $guide): array
    {
        $guide->load('media');
        $legacyUsers = GpUsuario::whereIn('id', $guide->media
            ->filter(fn (Media $media) => !$media->hasCustomProperty('user_name'))
            ->map(fn (Media $media) => $media->getCustomProperty('user_id'))->filter()->unique())
            ->get(['id', 'USERID', 'USERNAME'])->keyBy('id');
        $format = fn (Media $media) => [
            'id' => $media->id,
            'nombre' => $media->getCustomProperty('original_name', $media->file_name),
            'mime_type' => $media->mime_type,
            'tamano' => $media->size,
            'creado_en' => $media->created_at?->toIso8601String(),
            'nombre_firmante' => $media->getCustomProperty('nombre_firmante'),
            'identificacion_firmante' => $media->getCustomProperty('identificacion_firmante'),
            'latitud' => $media->getCustomProperty('latitud'),
            'longitud' => $media->getCustomProperty('longitud'),
            'precision_ubicacion' => $media->getCustomProperty('precision_ubicacion'),
            'fecha_firma' => $media->getCustomProperty('fecha_firma'),
            'subido_por' => [
                'id' => $media->getCustomProperty('user_id'),
                'nombre' => $media->getCustomProperty('user_name')
                    ?? $legacyUsers->get($media->getCustomProperty('user_id'))?->USERNAME,
                'usuario' => $media->getCustomProperty('user_login')
                    ?? $legacyUsers->get($media->getCustomProperty('user_id'))?->USERID,
            ],
            'subido_en' => $media->getCustomProperty('uploaded_at') ?? $media->created_at?->toIso8601String(),
        ];

        return [
            'firmas' => $guide->getMedia('firmas')->map($format)->values()->all(),
            'documentos' => $guide->getMedia('documentos')->map($format)->values()->all(),
        ];
    }

    protected function store(GpGuiaRemision|GpGuiaRemisionReserva $guide, array $files,
        string $collection, int $userId, ?string $signer = null, ?string $identification = null, array $location = []): array
    {
        $expected = $guide instanceof GpGuiaRemision && $guide->tipo === 'despacho' ? 'enviada' : 'recibida';
        if ($guide->estado !== $expected) {
            throw ValidationException::withMessages(['guia' => 'El estado de la guía no permite guardar archivos.']);
        }

        $user = GpUsuario::findOrFail($userId);
        $audit = [
            'user_id' => $userId,
            'user_name' => $user->USERNAME,
            'user_login' => $user->USERID,
            'uploaded_at' => now()->toIso8601String(),
        ];
        $created = [];
        try {
            foreach ($files as $file) {
                /** @var UploadedFile $file */
                $created[] = $guide->addMedia($file)
                    ->usingFileName(Str::uuid() . '.' . $file->guessExtension())
                    ->withCustomProperties($audit + [
                        'original_name' => basename($file->getClientOriginalName()),
                        'nombre_firmante' => $signer,
                        'identificacion_firmante' => $identification,
                        'latitud' => $location['latitud'] ?? null,
                        'longitud' => $location['longitud'] ?? null,
                        'precision_ubicacion' => $location['precision_ubicacion'] ?? null,
                        'fecha_firma' => $location['fecha_firma'] ?? null,
                    ])->toMediaCollection($collection);
            }
        } catch (\Throwable $exception) {
            foreach ($created as $media) {
                $media->delete();
            }
            throw $exception;
        }

        return $this->files($guide);
    }

    protected function download(GpGuiaRemision|GpGuiaRemisionReserva $guide, int $mediaId): StreamedResponse
    {
        $media = $guide->media()->whereIn('collection_name', ['firmas', 'documentos'])->findOrFail($mediaId);
        abort_unless(Storage::disk($media->disk)->exists($media->getPathRelativeToRoot()), 404, 'El archivo solicitado no fue encontrado.');
        return Storage::disk($media->disk)->response($media->getPathRelativeToRoot(), $media->file_name,
            ['Content-Type' => $media->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
