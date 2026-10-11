<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBuyerAssessmentRequest;
use App\Models\BuyerAssessment;
use App\Models\CtsRecord;
use App\Models\Reservation;
use App\Support\BuyerAssessmentData;
use App\Support\LockedReservationInformation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BuyerAssessmentController extends Controller
{
    public function update(SaveBuyerAssessmentRequest $request, Reservation $reservation): RedirectResponse
    {
        LockedReservationInformation::run($reservation, function (Reservation $locked) use ($request) {
            // Serialize assessment changes against the CTS completion transition.
            $transaction = $locked->transaction;
            if ($transaction && $transaction->ctsRecord) {
                $record = CtsRecord::whereKey($transaction->ctsRecord->id)->lockForUpdate()->firstOrFail();
                $transaction->setRelation('ctsRecord', $record);
                $locked->setRelation('transaction', $transaction);
            }
            Gate::authorize('updateAssessment', $locked);
            $assessment = $locked->buyerAssessment()->lockForUpdate()->first() ?? new BuyerAssessment;
            $assessment->reservation_id = $locked->id;
            if (! $assessment->exists) {
                $assessment->recorded_by = $request->user()->id;
                $assessment->privacy_notice_given_at = now();
            }
            $assessment->last_updated_by = $request->user()->id;
            $assessment->fill(BuyerAssessmentData::normalized($request->safe()->except(['privacy_notice_confirmed'])));
            $assessment->save();
        });

        return to_route('reservations.show', $reservation)->with('status', 'Buyer assessment saved.');
    }
}
