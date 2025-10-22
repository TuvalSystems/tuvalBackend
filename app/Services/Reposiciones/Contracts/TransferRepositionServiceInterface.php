<?php

namespace App\Services\Reposiciones\Contracts;

interface TransferRepositionServiceInterface
{

    public function validateRequest($request);
   public function fectchTransferHeader($request);
   public function processDocumentsInBatches($request, $transferHeader);

   public function buildResponse($processedDocuments);

     public function getDetailReceivedReposition($guideId);
}
