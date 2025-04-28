<?php

namespace App\Services\Reposiciones\Contracts;

interface TransferRepositionServiceInterface
{
   public function fectchTransferHeader($request);
   public function processDocumentsInBatches($request, $transferHeader);
}
