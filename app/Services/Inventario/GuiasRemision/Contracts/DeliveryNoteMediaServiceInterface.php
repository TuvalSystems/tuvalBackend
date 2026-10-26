<?php

namespace App\Services\Inventario\GuiasRemision\Contracts;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DeliveryNoteMediaServiceInterface
{
    public function getGuideFiles(string $type, int $guideId, int $userId): array;

    public function getConsultationFiles(string $type, int $guideId, int $userId): array;

    public function downloadConsultationFile(string $type, int $guideId, int $mediaId, int $userId): StreamedResponse;

    public function storeSignature(string $type, int $guideId, UploadedFile $signature,
        int $userId, ?string $signer = null, ?string $identification = null, array $location = []): array;

    public function storeDocuments(string $type, int $guideId, array $documents, int $userId): array;

    public function downloadGuideFile(string $type, int $guideId, int $mediaId, int $userId): StreamedResponse;
}
