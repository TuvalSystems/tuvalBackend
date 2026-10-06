<?php

namespace App\Services\Inventario\Reposiciones\Contracts;

use App\Models\GpUsuario;

interface TransferRepositionServiceInterface
{

    public function fectchTransferHeader(array $payload);
    public function processDocumentsInBatches(array $payload, $transferHeader);

    public function buildResponse($processedDocuments);

    public function getDetailReceivedReposition($guideId);

    public function processReceivedRepositionWeb($guideId, GpUsuario $user);
    public function getPendingRepositions();
}
