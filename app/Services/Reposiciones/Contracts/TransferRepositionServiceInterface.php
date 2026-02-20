<?php

namespace App\Services\Reposiciones\Contracts;

use App\Models\GpUsuario;

interface TransferRepositionServiceInterface
{

    public function validateRequest($request);
    public function fectchTransferHeader($request);
    public function processDocumentsInBatches($request, $transferHeader);

    public function buildResponse($processedDocuments);

    public function getDetailReceivedReposition($guideId);

    public function processReceivedRepositionWeb($guideId, GpUsuario $user);
}
