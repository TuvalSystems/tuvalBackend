<?php
namespace App\Services\Admin\Sync\Contracts;

interface SyncClientsServiceInterface
{
    // Define los métodos
     public function getClientsGp($fecha);
      public function handleClientSync(array $client);
}
