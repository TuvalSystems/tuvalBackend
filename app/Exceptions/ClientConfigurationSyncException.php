<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ClientConfigurationSyncException extends RuntimeException
{
    public function __construct(public readonly array $syncContext, Throwable $previous)
    {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
