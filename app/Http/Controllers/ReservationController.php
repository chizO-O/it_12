<?php

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\BuyerAssessment;
use App\Models\BuyerRequirement;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationActivity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Agent, UserRole::UnitManager], true), 403);

        $reservations = Reservation::query()->with(['lot.property', 'buyer:id,name', 'agent:id,name', 'reviewer:id,name'])
            ->when($user->role === UserRole::Buyer, fn ($query) => $query->where('buyer_id', $user->id))
            ->when($user->role === UserRole::Agent, fn ($query) => $query->where('agent_id', $user->id))
            ->when($user->role === UserRole::UnitManager, fn ($query) => $query->whereHas('agent', fn ($agents) => $agents->where('unit_manager_id', $user->id)))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(15);

        $notifications = $user->unreadNotifications()
            ->where('type', ReservationActivity::class)->latest()->limit(10)->get();

        return view('reservations.index', compact('reservations', 'notifications'));
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, [UserRole::Buyer, UserRole::Agent, UserRole::UnitManager], true), 403);
        $request->user()->unreadNotifications()->where('type', ReservationActivity::class)->update(['read_at' => now()]);

        return to_route('reservations.index')->with('status', 'Reservation notifications marked as read.');
    }

    public function store(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $agent = $request->user();
        abort_unless($agent->role === UserRole::Agent && $inquiry->agent_id === $agent->id, 403);
        $validated = $request->validate([
            'confirmed_interest' => ['required', 'accepted'],
            'agent_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $reservation = DB::transaction(function () use ($inquiry, $agent, $validated) {
                // Match the existing inventory lock order: property, lot, then inquiry.
                Property::whereKey($inquiry->lot->property_id)->lockForUpdate()->firstOrFail();
                $lot = Lot::whereKey($inquiry->lot_id)->lockForUpdate()->firstOrFail();
                $lockedInquiry = Inquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();

                abort_unless($lockedInquiry->agent_id === $agent->id && $lot->assigned_agent_id === $agent->id && $agent->hasVerifiedEmail() && $agent->unit_manager_id !== null, 403);
                if ($lot->status !== LotStatus::Available) {
                    throw ValidationException::withMessages(['reservation' => 'This lot is no longer Available. Refresh the inventory.']);
                }

                $reservation = new Reservation;
                $reservation->inquiry_id = $lockedInquiry->id;
                $reservation->lot_id = $lot->id;
                $reservation->buyer_id = $lockedInquiry->buyer_id;
                $reservation->agent_id = $agent->id;
                $reservation->status = ReservationStatus::Pending;
                $reservation->agent_notes = trim($validated['agent_notes'] ?? '') ?: null;
                $reservation->save();

                $lot->status = LotStatus::Reserved;
                $lot->save();

                return $reservation;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reservation' => 'This lot or inquiry already has an active reservation.']);
        }

        $reservation->buyer->notify(new ReservationActivity($reservation->id, 'Your selected lot has been reserved and is awaiting Unit Manager review.'));
        $managers = User::whereKey($reservation->agent->unit_manager_id)->where('role', UserRole::UnitManager->value)->whereNotNull('email_verified_at')->get();
        if ($managers->isNotEmpty()) {
            Notification::send($managers, new ReservationActivity($reservation->id, 'A new lot reservation is ready for your review.'));
        } else {
            Notification::send(
                User::where('role', UserRole::Admin->value)->whereNotNull('email_verified_at')->get(),
                new ReservationActivity($reservation->id, 'A reservation awaits review, but no verified Unit Manager account exists.')
            );
        }

        return to_route('reservations.show', $reservation)->with('status', $managers->isNotEmpty()
            ? 'Lot reserved. Unit Manager notified.'
            : 'Lot reserved. An Admin has been alerted to create or verify a Unit Manager account.');
    }

    public function show(Request $request, Reservation $reservation): View
    {
        $this->authorizeViewer($request->user(), $reservation);
        $reservation->load(['inquiry', 'lot.property', 'buyer:id,name,email', 'agent:id,name,unit_manager_id', 'reviewer:id,name', 'buyerForm', 'transaction.ctsRecord']);

        // Buyer views never retrieve internal remarks; Admin oversight does not include assessments.
        if (Gate::allows('viewAssessmentFacts', $reservation)) {
            $columns = Gate::allows('viewAssessment', $reservation) ? ['*']
                : ['id', 'reservation_id', 'recorded_by', 'last_updated_by', 'updated_at', ...array_keys(BuyerAssessment::FACT_LABELS)];
            $assessment = $reservation->buyerAssessment()->select($columns)->with(['recordedBy:id,name', 'lastUpdatedBy:id,name'])->first();
            $reservation->setRelation('buyerAssessment', $assessment);
        } else {
            $reservation->setRelation('buyerAssessment', null);
        }
        $corrections = Gate::allows('viewAssessmentFacts', $reservation)
            ? $reservation->informationCorrections()->with(['requester:id,name', 'reviewer:id,name'])->latest('id')->paginate(10)->withQueryString()
            : collect();

        return view('reservations.show', compact('reservation', 'corrections'));
    }

    public function details(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($request->user()->role === UserRole::Buyer && $reservation->buyer_id === $request->user()->id, 403);
        $data = $request->validateWithBag('contact', [
            'contact_number' => ['required', 'string', 'min:7', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($reservation, $data) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ReservationStatus::Pending) {
                throw ValidationException::withMessages(['reservation' => 'Contact information may only be edited while review is pending.']);
            }
            Gate::authorize('updateContact', $locked);
            $details = $locked->buyerForm()->first() ?? new BuyerRequirement;
            $details->reservation_id = $locked->id;
            $details->submitted_by = $locked->buyer_id;
            $details->requirement_type = 'buyer_form';
            $details->status = 'submitted';
            $details->fill($data);
            $details->submitted_at = now();
            $details->save();
        });

        return to_route('reservations.show', $reservation)->with('status', "Buyer's contact information saved for Unit Manager review.");
    }

    public function review(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($request->user()->role === UserRole::UnitManager && $reservation->agent->unit_manager_id === $request->user()->id, 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_notes' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ]);

        $this->withLockedReservation($reservation, function (Reservation $locked, Lot $lot) use ($request, $data) {
            if ($locked->status !== ReservationStatus::Pending || $lot->status !== LotStatus::Reserved) {
                throw ValidationException::withMessages(['reservation' => 'Only a pending reservation for a Reserved lot can be reviewed.']);
            }
            if ($data['decision'] === 'approve' && ! $locked->buyerForm()->exists()) {
                throw ValidationException::withMessages(['reservation' => 'The buyer must submit contact information before approval.']);
            }

            $locked->status = $data['decision'] === 'approve' ? ReservationStatus::Approved : ReservationStatus::Rejected;
            $locked->reviewed_by = $request->user()->id;
            $locked->review_notes = trim($data['review_notes'] ?? '') ?: null;
            $locked->reviewed_at = now();
            $locked->save();
            if ($locked->status === ReservationStatus::Rejected) {
                $lot->status = LotStatus::Available;
                $lot->save();
            }
        });

        $state = $data['decision'] === 'approve' ? 'approved for CTS processing' : 'rejected';
        $this->notifyParticipants($reservation, 'Your lot reservation was '.$state.'.');

        return to_route('reservations.show', $reservation)->with('status', 'Reservation '.$state.'.');
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            ($user->role === UserRole::Agent && $reservation->agent_id === $user->id)
            || ($user->role === UserRole::UnitManager && $reservation->agent->unit_manager_id === $user->id),
            403
        );
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $this->withLockedReservation($reservation, function (Reservation $locked, Lot $lot) use ($user, $data) {
            if ($locked->transaction()->exists()) {
                throw ValidationException::withMessages(['reservation' => 'A CTS process is already underway. Contact the office before changing this reservation.']);
            }
            if (! $locked->status->isActive() || $lot->status !== LotStatus::Reserved) {
                throw ValidationException::withMessages(['reservation' => 'Only an active reservation can be cancelled.']);
            }
            if ($user->role === UserRole::Agent && $locked->status !== ReservationStatus::Pending) {
                abort(403, 'Only the Unit Manager may cancel an approved reservation.');
            }
            $locked->status = ReservationStatus::Cancelled;
            $locked->review_notes = trim($data['reason']);
            $locked->reviewed_by = $user->role === UserRole::UnitManager ? $user->id : null;
            $locked->reviewed_at = now();
            $locked->save();

            $lot->status = LotStatus::Available;
            $lot->save();
        });

        $this->notifyParticipants($reservation, 'The lot reservation was cancelled.');

        return to_route('reservations.show', $reservation)->with('status', 'Reservation cancelled and lot returned to Available.');
    }

    private function withLockedReservation(Reservation $reservation, callable $callback): void
    {
        DB::transaction(function () use ($reservation, $callback) {
            // Same lock order as creation and existing property/lot CRUD.
            Property::whereKey($reservation->lot->property_id)->lockForUpdate()->firstOrFail();
            $lot = Lot::whereKey($reservation->lot_id)->lockForUpdate()->firstOrFail();
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $callback($locked, $lot);
        }, 3);
    }

    private function authorizeViewer(User $user, Reservation $reservation): void
    {
        abort_unless(
            ($user->role === UserRole::Buyer && $reservation->buyer_id === $user->id)
            || ($user->role === UserRole::Agent && $reservation->agent_id === $user->id)
            || $user->role === UserRole::Admin
            || ($user->role === UserRole::UnitManager && $reservation->agent->unit_manager_id === $user->id),
            403
        );
    }

    private function notifyParticipants(Reservation $reservation, string $description): void
    {
        $reservation->load(['buyer', 'agent']);
        $reservation->buyer->notify(new ReservationActivity($reservation->id, $description));
        $reservation->agent->notify(new ReservationActivity($reservation->id, $description));
    }
}
