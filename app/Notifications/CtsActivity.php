<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CtsActivity extends Notification
{
    use Queueable;

    public function __construct(private readonly int $recordId, private readonly string $description) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['cts_record_id' => $this->recordId, 'description' => $this->description];
    }
}
