<?php

namespace App\Services\Notificacion;

use App\Models\Notificaciones;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
class NotificationService implements NotificationServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function sendNotification($userId,$title, $message)
    {
        Notificaciones::create([
            'user_id' => $userId,
            'titulo' => $title,
            'mensaje' => $message,
            'estado' => 'nuevo',
        ]);

    }
}
