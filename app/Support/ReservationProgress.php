<?php

namespace App\Support;

use App\Enums\LotStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;

/** Buyer-facing reservation life cycle, intentionally separate from CTS document states. */
final class ReservationProgress
{
    public static function label(Reservation $reservation): string
    {
        if ($reservation->status === ReservationStatus::Pending) return 'Pending review';
        if ($reservation->status === ReservationStatus::Rejected) return 'Rejected';
        if ($reservation->status === ReservationStatus::Cancelled) return 'Cancelled';

        $transaction = $reservation->transaction;
        $lot = $reservation->lot;
        if ($lot?->status === LotStatus::FullyPaid || $transaction?->status === 'fully_paid') return 'Fully paid';
        if ($lot?->status === LotStatus::Sold || $transaction?->status === 'sold') return 'Sold — payment ongoing';
        if ($transaction?->status === 'cts_completed') return 'CTS completed — sale pending';
        if ($transaction?->status === 'cts_processing') return 'CTS processing';

        return 'Eligible for sale';
    }

    public static function badgeClass(Reservation $reservation): string
    {
        if ($reservation->status === ReservationStatus::Rejected || $reservation->status === ReservationStatus::Cancelled) {
            return 'bg-stone-100 text-stone-700';
        }
        if ($reservation->lot?->status === LotStatus::FullyPaid || $reservation->transaction?->status === 'fully_paid') {
            return 'bg-violet-100 text-violet-900';
        }
        if ($reservation->lot?->status === LotStatus::Sold || $reservation->transaction?->status === 'sold') {
            return 'bg-sky-100 text-sky-900';
        }
        if ($reservation->transaction?->status === 'cts_completed') return 'bg-emerald-100 text-emerald-900';
        return 'bg-amber-100 text-amber-900';
    }
}
