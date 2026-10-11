<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved for CTS processing',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }
}
