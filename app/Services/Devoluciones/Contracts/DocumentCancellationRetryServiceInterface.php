<?php
namespace App\Services\Devoluciones\Contracts;



interface DocumentCancellationRetryServiceInterface
{
    public function handleCancellationRetries();
    public function handleSendCancellationsWithError();
}
