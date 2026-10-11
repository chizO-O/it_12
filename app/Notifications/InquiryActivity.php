<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InquiryActivity extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $inquiryId,
        private readonly string $description,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['inquiry_id' => $this->inquiryId, 'description' => $this->description];
    }
}
