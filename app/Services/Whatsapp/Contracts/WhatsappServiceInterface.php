<?php
namespace App\Services\Whatsapp\Contracts;

interface WhatsappServiceInterface
{
    // Define los métodos
      public function sendOrderReadyNotification(
        string $phone,
        string $customerName,
        string $orderNumber,
        string $branchName,
        ?string $imageUrl = null,
        ?string $pdfUrl = null,
        ?string $pdfFilename = null
    );
}
