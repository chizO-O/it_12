<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReservationActivity extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $reservationId,
        private readonly string $description,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reservation_id' => $this->reservationId,
            'description' => $this->description,
        ];
    }
}
