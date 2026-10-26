<?php
namespace App\Services\Admin\Menu\Contracts;

interface MenuServiceInterface
{
    // Define los métodos

    public function getFullMenuLayout(int $perfilId): array;
}
