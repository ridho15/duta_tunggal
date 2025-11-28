<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class FilamentDatabaseNotification extends Notification
{
    public function __construct(
        private string $title,
        private ?string $body = null,
        private ?string $icon = null,
        private ?string $color = null,
        private array $actions = []
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'color' => $this->color,
            'duration' => 'persistent',
            'format' => 'filament',
            'actions' => $this->actions,
            'view' => 'filament-notifications::notification',
            'viewData' => [],
            'status' => null,
            'iconColor' => null,
        ];
    }
}