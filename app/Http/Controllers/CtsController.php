<?php

namespace App\Http\Controllers;

use App\Enums\CtsStatus;
use App\Enums\LotStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CtsRecord;
use App\Models\Lot;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\CtsActivity;
use App\Support\PrivateCtsStorage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CtsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Admin, UserRole::FieldPlanner], true), 403);

        $records = CtsRecord::query()->with(['transaction.reservation.lot.property', 'transaction.reservation.buyer', 'initiator'])
            ->when($user->role === UserRole::Buyer, fn ($q) => $q->whereHas('transaction.reservation', fn ($r) => $r->where('buyer_id', $user->id)))
            ->when($user->role === UserRole::Agent, fn ($q) => $q->whereHas('transaction.reservation', fn ($r) => $r->where('agent_id', $user->id)))
            ->when($user->role === UserRole::UnitManager, fn ($q) => $q->whereHas('transaction.reservation.agent', fn ($a) => $a->where('unit_manager_id', $user->id)))
            ->latest('updated_at')->paginate(15);

        // Secretary can prepare a CTS directly from any approved reservation,
        // without an extra Unit Manager initiation step.
        $readyReservations = $user->role === UserRole::Admin
            ? Reservation::query()->with(['buyer', 'lot.property'])
                ->where('status', ReservationStatus::Approved->value)
                ->whereDoesntHave('transaction')->latest()->get()
            : collect();

        return view('cts.index', compact('records', 'readyReservations'));
    }

    public function initiate(Request $request, Reservation $reservation): RedirectResponse
    {
        Gate::authorize('initiate', [CtsRecord::class, $reservation]);
        try {
            $cts = DB::transaction(function () use ($request, $reservation) {
                Property::whereKey($reservation->lot->property_id)->lockForUpdate()->firstOrFail();
                $lot = Lot::whereKey($reservation->lot_id)->lockForUpdate()->firstOrFail();
                $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('initiate', [CtsRecord::class, $locked]);
                if ($locked->status !== ReservationStatus::Approved || $lot->status !== LotStatus::Reserved) {
                    throw ValidationException::withMessages(['cts' => 'CTS requires an approved reservation and a Reserved lot.']);
                }
                if ($locked->transaction()->exists()) {
                    throw ValidationException::withMessages(['cts' => 'A CTS has already been opened for this reservation.']);
                }
                $transaction = new Transaction;
                $transaction->reservation_id = $locked->id;
                $transaction->status = 'cts_processing';
                $transaction->save();

                $cts = new CtsRecord;
                $cts->transaction_id = $transaction->id;
                $cts->initiated_by_id = $request->user()->id;
                $cts->status = CtsStatus::Requested;
                $cts->save();

                return $cts;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['cts' => 'A CTS already exists for this reservation.']);
        }
        $this->notifyRoles([UserRole::FieldPlanner], $cts, 'A new CTS needs seller/landowner verification.');

        return to_route('cts.show', $cts)->with('status', 'CTS preparation opened. The Field Planner can now verify the seller/landowner.');
    }

    /** Resolve an approved reservation to its accessible CTS record; no data is created by GET. */
    public function forReservation(Reservation $reservation): RedirectResponse
    {
        $cts = $reservation->transaction?->ctsRecord;
        abort_unless($cts, 404);
        Gate::authorize('view', $cts);

        return to_route('cts.show', $cts);
    }

    public function show(Request $request, CtsRecord $cts): View
    {
        Gate::authorize('view', $cts);
        $cts->load(['transaction.reservation.lot.property', 'transaction.reservation.buyer', 'transaction.reservation.buyerForm', 'transaction.reservation.agent', 'initiator', 'verifier']);

        return view('cts.show', compact('cts'));
    }

    /** Buyer provides CTS-specific identity details, independently of locked contact details. */
    public function buyerDetails(Request $request, CtsRecord $cts): RedirectResponse
    {
        Gate::authorize('editBuyerDetails', $cts);
        $data = $request->validate([
            'buyer_full_name' => ['required', 'string', 'min:2', 'max:200'],
            'buyer_permanent_address' => ['required', 'string', 'max:1500'],
            'buyer_marital_status' => ['required', Rule::in(['Single', 'Married', 'Widowed', 'Legally Separated', 'Divorced', 'Other'])],
            'buyer_age' => ['required', 'integer', 'between:1,120'],
            'buyer_nationality' => ['required', 'string', 'max:100'],
        ]);
        $alreadyReady = $cts->partiesReady();
        $this->change($cts, 'editBuyerDetails', function (CtsRecord $locked) use ($data) {
            foreach ($data as $field => $value) {
                $locked->{$field} = $value;
            }
            $locked->buyer_details_submitted_at ??= now();
        });

        $this->notifyWhenPartiesReady($cts, $alreadyReady);
        return to_route('cts.show', $cts)->with('status', 'Your CTS buyer details were saved.');
    }

    public function verify(Request $request, CtsRecord $cts): RedirectResponse
    {
        Gate::authorize('verifyProperty', $cts);
        $data = $request->validate([
            'verified' => ['required', 'accepted'],
            'landowner_name' => ['required', 'string', 'max:200'],
            'landowner_contact' => ['required', 'string', 'min:7', 'max:40'],
            'landowner_address' => ['required', 'string', 'max:1500'],
            'landowner_permanent_address' => ['required', 'string', 'max:1500'],
            'landowner_marital_status' => ['required', Rule::in(['Single', 'Married', 'Widowed', 'Legally Separated', 'Divorced', 'Other'])],
            'landowner_age' => ['required', 'integer', 'between:1,120'],
            'landowner_nationality' => ['required', 'string', 'max:100'],
            'landowner_relationship' => ['nullable', 'string', 'max:150'],
            'verification_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $alreadyReady = $cts->partiesReady();
        $this->change($cts, 'verifyProperty', function (CtsRecord $locked) use ($request, $data) {
            // Do not reset a CTS draft/review/signing status when landowner details are edited.
            if ($locked->status === CtsStatus::Requested) {
                $locked->status = CtsStatus::PropertyVerified;
            }
            $locked->verified_by_id = $request->user()->id;
            $locked->property_verified_at ??= now();
            $locked->landowner_name = $data['landowner_name'];
            $locked->landowner_contact = $data['landowner_contact'];
            $locked->landowner_address = $data['landowner_address'];
            $locked->landowner_permanent_address = $data['landowner_permanent_address'] ?? null;
            $locked->landowner_marital_status = $data['landowner_marital_status'] ?? null;
            $locked->landowner_age = $data['landowner_age'] ?? null;
            $locked->landowner_nationality = $data['landowner_nationality'] ?? null;
            $locked->landowner_relationship = $data['landowner_relationship'] ?? null;
            $locked->verification_notes = $data['verification_notes'] ?? null;
        });
        $this->notifyWhenPartiesReady($cts, $alreadyReady);

        return to_route('cts.show', $cts)->with('status', 'Landowner verification information saved.');
    }

    /** Secretary records agreed contract facts; the executed PDF remains legally authoritative. */
    public function prepare(Request $request, CtsRecord $cts): RedirectResponse
    {
        Gate::authorize('prepare', $cts);
        if (! $cts->draft_path && ! $cts->partiesReady()) {
            throw ValidationException::withMessages(['cts' => 'Wait until the Buyer and Field Planner submit all CTS identity details.']);
        }
        $data = $request->validate([
            'contract_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'ownership_agreement' => ['required', 'string', 'min:10', 'max:6000'],
            'payment_agreement' => ['required', 'string', 'min:10', 'max:6000'],
            'terms_verified' => ['required', 'accepted'],
        ]);
        $this->change($cts, 'prepare', function (CtsRecord $locked) use ($request, $data) {
            $locked->contract_price = $data['contract_price'];
            $locked->ownership_agreement = trim($data['ownership_agreement']);
            $locked->payment_agreement = trim($data['payment_agreement']);
            $locked->contract_prepared_by_id = $request->user()->id;
            $locked->contract_prepared_at = now();
        });

        return to_route('cts.show', $cts)->with('status', 'CTS details saved. Upload the externally prepared PDF after landowner verification.');
    }

    public function uploadDraft(Request $request, CtsRecord $cts, PrivateCtsStorage $storage): RedirectResponse
    {
        Gate::authorize('uploadDraft', $cts);
        if ($cts->contract_price === null || ! $cts->ownership_agreement || ! $cts->payment_agreement) {
            throw ValidationException::withMessages(['cts' => 'Secretary must save the CTS price and agreement details before uploading the draft.']);
        }
        // New CTS drafts require both parties' relevant contract details.
        // Existing draft versions remain accessible for legacy records and amendments.
        if (! $cts->draft_path && ! $cts->partiesReady()) {
            throw ValidationException::withMessages([
                'cts' => 'Buyer CTS details and complete Field Planner seller details are required before uploading the first draft.',
            ]);
        }
        $request->validate(['document' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $path = $storage->upload($request->file('document'), $cts->id, 'draft');
        $this->change($cts, 'uploadDraft', function (CtsRecord $locked) use ($request, $path) {
            $locked->draft_path = $path;
            $locked->uploaded_by_id = $request->user()->id;
            $locked->draft_uploaded_at = now();
            $locked->draft_version++;
            $locked->status = CtsStatus::ForBuyerReview;
            $locked->buyer_reviewed_at = null;
            $locked->buyer_feedback = null;
        });
        $this->notifyBuyerAndAgent($cts, 'A CTS draft is ready for Buyer review.');

        return to_route('cts.show', $cts)->with('status', 'CTS draft uploaded to private Supabase Storage.');
    }

    public function review(Request $request, CtsRecord $cts): RedirectResponse
    {
        Gate::authorize('review', $cts);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['acknowledge', 'request_revision'])],
            'feedback' => ['required_if:decision,request_revision', 'nullable', 'string', 'max:3000'],
        ]);
        $this->change($cts, 'review', function (CtsRecord $locked) use ($data) {
            $locked->buyer_reviewed_at = now();
            $locked->buyer_feedback = trim($data['feedback'] ?? '') ?: null;
            $locked->status = $data['decision'] === 'acknowledge' ? CtsStatus::ForSigning : CtsStatus::RevisionRequested;
        });
        $this->notifyRoles([UserRole::Admin], $cts, $data['decision'] === 'acknowledge'
            ? 'Buyer reviewed the CTS. External signing/notarization is required.'
            : 'Buyer requested CTS revisions.');

        return to_route('cts.show', $cts)->with('status', $data['decision'] === 'acknowledge'
            ? 'Your document review was recorded. This is NOT an electronic legal signature.'
            : 'Your requested changes were sent for review.');
    }

    public function complete(Request $request, CtsRecord $cts, PrivateCtsStorage $storage): RedirectResponse
    {
        Gate::authorize('complete', $cts);
        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'notarized_at' => ['required', 'date', 'before_or_equal:today'],
            'external_process_confirmed' => ['required', 'accepted'],
        ]);
        $path = $storage->upload($request->file('document'), $cts->id, 'executed');
        $this->change($cts, 'complete', function (CtsRecord $locked) use ($request, $data, $path) {
            $locked->executed_path = $path;
            $locked->executed_uploaded_by_id = $request->user()->id;
            $locked->executed_uploaded_at = now();
            $locked->notarized_at = $data['notarized_at'];
            $locked->completed_at = now();
            $locked->status = CtsStatus::Completed;
            // Intentionally do not mark the lot Sold or create payment schedules.
            $locked->transaction->status = 'cts_completed';
            $locked->transaction->save();
        });
        $this->notifyBuyerAndAgent($cts, 'The externally executed CTS has been recorded.');

        return to_route('cts.show', $cts)->with('status', 'Executed and notarized CTS recorded. Lot remains Reserved until sale processing.');
    }

    public function document(Request $request, CtsRecord $cts, string $kind, PrivateCtsStorage $storage): RedirectResponse
    {
        Gate::authorize('viewDocument', $cts);
        abort_unless(in_array($kind, ['draft', 'executed'], true), 404);
        $path = $kind === 'draft' ? $cts->draft_path : $cts->executed_path;
        abort_unless($path, 404);

        return redirect()->away($storage->signedUrl($path));
    }

    /** Notify the office only on the transition from incomplete to complete parties. */
    private function notifyWhenPartiesReady(CtsRecord $cts, bool $wasReady): void
    {
        $cts->refresh();
        if (! $wasReady && $cts->partiesReady()) {
            $this->notifyRoles([UserRole::Admin], $cts, 'Buyer and seller CTS information is complete. Prepare the contract draft.');
        }
    }

    /** Recheck authorization and allowed state under row lock. */
    private function change(CtsRecord $cts, string $ability, callable $callback): void
    {
        DB::transaction(function () use ($cts, $ability, $callback) {
            $locked = CtsRecord::whereKey($cts->id)->lockForUpdate()->firstOrFail();
            Gate::authorize($ability, $locked);
            $callback($locked);
            $locked->save();
        }, 3);
    }

    private function notifyRoles(array $roles, CtsRecord $cts, string $message): void
    {
        $users = User::whereIn('role', array_map(fn (UserRole $role) => $role->value, $roles))
            ->whereNotNull('email_verified_at')->get();
        Notification::send($users, new CtsActivity($cts->id, $message));
    }

    private function notifyBuyerAndAgent(CtsRecord $cts, string $message): void
    {
        $cts->load('transaction.reservation');
        $reservation = $cts->transaction->reservation;
        User::whereIn('id', [$reservation->buyer_id, $reservation->agent_id])->get()
            ->each(fn (User $user) => $user->notify(new CtsActivity($cts->id, $message)));
    }
}
