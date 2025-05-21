<?php

namespace App\Services\Notificacion\Contracts;

interface NotificationServiceInterface
{
   public function sendNotification($userId, $title, $message);
}
