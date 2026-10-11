<?php

namespace App\Support;

use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class LockedReservationInformation
{
    public static function run(Reservation $reservation, callable $callback): mixed
    {
        return DB::transaction(function () use ($reservation, $callback) {
            // Use the reservation/inventory lock order; re-read assignments before authorizing writes.
            Property::whereKey($reservation->lot->property_id)->lockForUpdate()->firstOrFail();
            $lot = Lot::whereKey($reservation->lot_id)->lockForUpdate()->firstOrFail();
            $inquiry = Inquiry::whereKey($reservation->inquiry_id)->lockForUpdate()->firstOrFail();
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('lot', $lot)->setRelation('inquiry', $inquiry);

            return $callback($locked);
        }, 3);
    }
}
