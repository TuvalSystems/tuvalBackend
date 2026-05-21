<?php
namespace App\Services\Movil\Gestion\Contracts;

interface SteamTrapServiceInterface
{
    public function generateSizingPdf(array $data): string;
}
