<?php
namespace App\Services\Movil\Gestion\Contracts;

use App\Models\GpUsuario;

interface SteamTrapServiceInterface
{
    public function generateSizingPdf(array $data): string;
    public function generateQuoteDraft(array $payload, GpUsuario $user) :mixed;
}
