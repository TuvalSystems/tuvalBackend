<?php
namespace App\Services\Admin\Contracts;

interface MenuServiceInterface
{
    // Define los métodos

    public function getFullMenuLayout(int $perfilId): array;
}
