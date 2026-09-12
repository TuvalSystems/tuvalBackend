<?php

namespace App\Notifications\User;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TestUserNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public array $data = []
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        return [
            'title'     => $this->title,
            'message'   => $this->message,
            'data'      => $this->data,
        ];
    }
}
