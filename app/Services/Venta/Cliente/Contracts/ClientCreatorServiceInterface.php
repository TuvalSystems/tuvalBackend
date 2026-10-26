<?php
namespace App\Services\Venta\Cliente\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface ClientCreatorServiceInterface
{
    // Define los métodos

    public function createClient(array $data, GpUsuario $user);
}
