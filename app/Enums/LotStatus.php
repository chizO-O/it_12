<?php

namespace App\Enums;

enum LotStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case FullyPaid = 'fully_paid';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::Sold => 'Sold',
            self::FullyPaid => 'Fully Paid',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            self::Reserved => 'border-amber-200 bg-amber-50 text-amber-800',
            self::Sold => 'border-rose-200 bg-rose-50 text-rose-800',
            self::FullyPaid => 'border-violet-200 bg-violet-50 text-violet-800',
        };
    }
}
