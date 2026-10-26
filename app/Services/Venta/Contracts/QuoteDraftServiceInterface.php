<?php

namespace App\Services\Venta\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface QuoteDraftServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function sync(string $draftId, int $pvId, string $ptoVtaId, array $data, GpUsuario $user);
    public function getDraft(string $draftId, GpUsuario $user);
    public function listDrafts(string $ptoVtaId, GpUsuario $user);
    public function deleteDraft(string $draftId, GpUsuario $user);
}
