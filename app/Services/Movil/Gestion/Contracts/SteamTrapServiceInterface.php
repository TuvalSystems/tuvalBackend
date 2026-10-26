<?php
namespace App\Services\Movil\Gestion\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface SteamTrapServiceInterface
{
    public function generateSizingPdf(array $data, GpUsuario $user): string;
    public function generateQuoteDraft(array $payload, GpUsuario $user) :mixed;
}
