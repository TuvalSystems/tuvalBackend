<?php

namespace App\Enums\Admin\Sync;

enum StatusSync : string
{
    case FAILED   = 'failed';
    case RESOLVED = 'resolved';
    case WARNING  = 'warning';
    case PENDING  = 'pending';



   public function label(): string
    {
        return match($this) {
            self::FAILED   => 'Error de Sincronización',
            self::RESOLVED => 'Resuelto Manualmente',
            self::WARNING  => 'Advertencia / Reintento',
            self::PENDING  => 'Pendiente de Procesar',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::FAILED   => 'danger',
            self::RESOLVED => 'success',
            self::WARNING  => 'warning',
            self::PENDING  => 'info',
        };
    }
}