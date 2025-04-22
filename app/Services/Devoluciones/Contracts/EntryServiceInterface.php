<?php

namespace App\Services\Devoluciones\Contracts;

interface EntryServiceInterface
{
    public function registerEntity( $type, $document, $detail );
}
