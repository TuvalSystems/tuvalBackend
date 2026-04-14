<?php

namespace App\Services\Admin\Sync\Contracts;

interface SyncClientsServiceInterface
{
    // Define los métodos
    public function getClientsGp($fecha);
    public function handleClientSync(array $client);

    public function getBranchesGp($fecha);
    public function handleClientBranchSync(array $data);

     public function getClientsCoordinates();

     public function handleClientBranchCoordinatesSync(array $data);

}
