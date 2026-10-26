<?php

namespace App\Services\Devoluciones\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface EntryServiceInterface
{
    public function registerEntity( $type, $document, $detail , GpUsuario $user);
}
