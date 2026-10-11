<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\BuyerAssessment;
use App\Models\BuyerInformationCorrection;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationActivity;
use App\Support\BuyerAssessmentData;
use App\Support\LockedReservationInformation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuyerInformationCorrectionController extends Controller
{
    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        Gate::authorize('requestCorrection', $reservation);
        $data = $request->validateWithBag('correction', [
            'section' => ['required', Rule::in(['contact', 'assessment'])],
            'field' => ['required', 'string'],
            'proposed_value' => ['nullable', 'string', 'max:4000'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        LockedReservationInformation::run($reservation, function (Reservation $locked) use ($request, $data) {
            Gate::authorize('requestCorrection', $locked);
            if ($data['section'] === 'contact') {
                abort_unless($request->user()->role === UserRole::Buyer && $locked->buyer_id === $request->user()->id, 403);
                if ($locked->status === ReservationStatus::Pending) {
                    throw ValidationException::withMessages(['correction' => 'Use Edit Information to update your contact information while review is pending.']);
                }
                $record = $locked->buyerForm()->first();
                $rules = [
                    'contact_number' => ['required', 'string', 'min:7', 'max:30'],
                    'address' => ['required', 'string', 'max:1000'],
                    'notes' => ['nullable', 'string', 'max:2000'],
                ];
            } else {
                $record = $locked->buyerAssessment()->first();
                $rules = array_intersect_key(BuyerAssessmentData::rules(), BuyerAssessment::FACT_LABELS);
            }
            if (! $record) {
                throw ValidationException::withMessages(['correction' => 'There is no submitted record to correct yet.']);
            }
            Validator::make(['field' => $data['field']], ['field' => [Rule::in(array_keys($rules))]])->validateWithBag('correction');
            $field = $data['field'];
            if (in_array($field, ['requested_installment_months', 'proposed_down_payment'], true) && $record->preferred_payment_method !== 'installment') {
                throw ValidationException::withMessages(['correction' => 'Installment details can only be corrected for an installment preference.']);
            }
            $inputValue = $data['proposed_value'] ?? null;
            if ($field === 'preferred_payment_method' && is_string($inputValue)) {
                $inputValue = match (strtolower($inputValue)) {
                    'spot cash' => 'spot_cash',
                    'installment' => 'installment',
                    default => $inputValue,
                };
            }
            $value = Validator::make(['proposed_value' => $inputValue], ['proposed_value' => $rules[$field]])->validateWithBag('correction')['proposed_value'];
            $original = $this->snapshot($record, $data['section']);
            $proposed = [$field => $value];
            if ($data['section'] === 'assessment') {
                $normalized = BuyerAssessmentData::normalized(array_replace($original, $proposed));
                $model = new BuyerAssessment($normalized);
                $proposed = [$field => $model->getAttribute($field)];
                if ($field === 'preferred_payment_method' && $value !== 'installment') {
                    $proposed += ['requested_installment_months' => null, 'proposed_down_payment' => null];
                }
            }
            if (array_intersect_key($original, $proposed) === $proposed) {
                throw ValidationException::withMessages(['correction' => 'The proposed information matches the current record.']);
            }
            if ($locked->informationCorrections()->where('section', $data['section'])->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['correction' => 'A correction for this section is already awaiting Unit Manager review.']);
            }
            $correction = new BuyerInformationCorrection;
            $correction->reservation_id = $locked->id;
            $correction->requested_by = $request->user()->id;
            $correction->section = $data['section'];
            $correction->status = 'pending';
            $correction->original_values = $original;
            $correction->proposed_values = $proposed;
            $correction->reason = $data['reason'];
            $correction->save();
        });

        User::whereKey($reservation->agent->unit_manager_id)->where('role', UserRole::UnitManager->value)
            ->whereNotNull('email_verified_at')->first()?->notify(new ReservationActivity($reservation->id, 'A buyer-information correction is awaiting your review.'));

        return to_route('reservations.show', $reservation)->with('status', 'Correction requested. The existing record remains unchanged until Unit Manager approval.');
    }

    public function review(Request $request, Reservation $reservation, BuyerInformationCorrection $correction): RedirectResponse
    {
        abort_unless($correction->reservation_id === $reservation->id, 404);
        Gate::authorize('reviewCorrection', $reservation);
        $data = $request->validateWithBag('correctionReview', [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_notes' => ['required', 'string', 'max:2000'],
        ]);
        LockedReservationInformation::run($reservation, function (Reservation $locked) use ($request, $correction, $data) {
            Gate::authorize('reviewCorrection', $locked);
            $current = $locked->informationCorrections()->whereKey($correction->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'pending') {
                throw ValidationException::withMessages(['correction' => 'This correction has already been reviewed.']);
            }
            if ($data['decision'] === 'approve') {
                $record = $current->section === 'contact'
                    ? $locked->buyerForm()->lockForUpdate()->firstOrFail()
                    : $locked->buyerAssessment()->lockForUpdate()->firstOrFail();
                if ($this->snapshot($record, $current->section) !== $current->original_values) {
                    throw ValidationException::withMessages(['correction' => 'The information changed after this request. Reject it and request a new correction to avoid overwriting newer information.']);
                }
                $record->fill($current->proposed_values);
                if ($record instanceof BuyerAssessment) {
                    $record->last_updated_by = $request->user()->id;
                }
                $record->save();
            }
            $current->status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
            $current->reviewed_by = $request->user()->id;
            $current->reviewed_at = now();
            $current->review_notes = $data['review_notes'];
            $current->save();
        });

        foreach ([$reservation->buyer, $reservation->agent] as $participant) {
            $participant->notify(new ReservationActivity($reservation->id, 'A buyer-information correction was '.($data['decision'] === 'approve' ? 'approved' : 'rejected').' by the Unit Manager.'));
        }

        return to_route('reservations.show', $reservation)->with('status', $data['decision'] === 'approve' ? 'Correction approved and applied. The audit record has been retained.' : 'Correction rejected. The existing information has been retained.');
    }

    private function snapshot($record, string $section): array
    {
        return $section === 'assessment' ? $record->facts()
            : collect(array_keys(BuyerInformationCorrection::CONTACT_LABELS))->mapWithKeys(fn ($field) => [$field => $record->getAttribute($field)])->all();
    }
}
