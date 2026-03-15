<?php

namespace App\Services\Devoluciones\Contracts;

use App\Models\GpUsuario;

interface EntryServiceInterface
{
    public function registerEntity( $type, $document, $detail , GpUsuario $user);
}
