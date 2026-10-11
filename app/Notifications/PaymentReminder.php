<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentReminder extends Notification
{
    use Queueable;

    public function __construct(private readonly int $transactionId, private readonly string $description) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['transaction_id' => $this->transactionId, 'description' => $this->description, 'category' => 'payment_reminder'];
    }
}
