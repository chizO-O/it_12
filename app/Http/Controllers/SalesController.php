<?php

namespace App\Http\Controllers;

use App\Enums\CtsStatus;
use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\SaleActivity;
use App\Support\PesoAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Cashier, UserRole::UnitManager], true), 403);
        $transactions = Transaction::query()->with(['reservation.buyer', 'reservation.agent', 'reservation.lot.property', 'ctsRecord'])
            ->when($user->role === UserRole::Buyer, fn ($q) => $q->whereHas('reservation', fn ($r) => $r->where('buyer_id', $user->id)))
            ->when($user->role === UserRole::UnitManager, fn ($q) => $q
                ->where('status', 'cts_completed')
                ->whereNull('terms_confirmed_at')
                ->whereHas('reservation.agent', fn ($a) => $a->where('unit_manager_id', $user->id)))
            ->latest()->paginate(15);

        return view('sales.index', compact('transactions'));
    }

    public function show(Transaction $transaction): View
    {
        Gate::authorize('view', $transaction);
        $transaction->load(['reservation.buyer', 'reservation.agent', 'reservation.lot.property', 'ctsRecord', 'paymentSchedules', 'payments.recorder']);
        $total = $transaction->agreed_price === null ? 0 : PesoAmount::cents($transaction->agreed_price);
        $paid = $transaction->payments->sum(fn (Payment $payment) => PesoAmount::cents($payment->amount));
        return view('sales.show', ['transaction' => $transaction, 'totalCents' => $total, 'paidCents' => $paid, 'balanceCents' => max(0, $total - $paid)]);
    }

    /** The responsible Unit Manager records externally agreed terms after final CTS. */
    public function confirm(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('confirmTerms', $transaction);
        $data = $request->validate([
            'agreed_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'payment_type' => ['required', Rule::in(['spot_cash', 'installment'])],
            'down_payment' => ['required_if:payment_type,installment', 'nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
            'term_months' => ['required_if:payment_type,installment', 'nullable', 'integer', 'between:1,120'],
            'first_due_date' => ['required_if:payment_type,installment', 'nullable', 'date', 'after_or_equal:today'],
            'terms_confirmed' => ['required', 'accepted'],
        ]);
        $totalCents = PesoAmount::cents($data['agreed_price']);
        $installment = $data['payment_type'] === 'installment';
        $downCents = $installment ? PesoAmount::cents($data['down_payment'] ?? '0') : 0;
        if ($downCents >= $totalCents) {
            throw ValidationException::withMessages(['down_payment' => 'Down payment must be less than the agreed price. Use Spot Cash for full payment.']);
        }
        if ($installment && $totalCents - $downCents < (int) $data['term_months']) {
            throw ValidationException::withMessages(['term_months' => 'Each monthly installment must be at least ₱0.01. Choose fewer months.']);
        }

        DB::transaction(function () use ($request, $transaction, $data, $installment, $totalCents, $downCents) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $lot = Lot::whereKey($locked->reservation->lot_id)->lockForUpdate()->firstOrFail();
            $locked->reservation->setRelation('lot', $lot);
            Gate::authorize('confirmTerms', $locked);
            // Prevent a silent mismatch between the completed CTS and confirmed sale price.
            if ($locked->ctsRecord?->contract_price !== null
                && PesoAmount::cents($locked->ctsRecord->contract_price) !== $totalCents) {
                throw ValidationException::withMessages(['agreed_price' => 'The sale price must match the executed Contract to Sell. Resolve any contract amendment with the Secretary first.']);
            }
            $locked->agreed_price = PesoAmount::decimal($totalCents);
            $locked->payment_type = $data['payment_type'];
            $locked->term_months = $installment ? (int) $data['term_months'] : null;
            $locked->down_payment = PesoAmount::decimal($downCents);
            $locked->confirmed_by_id = $request->user()->id;
            $locked->terms_confirmed_at = now();
            $locked->status = 'sold';
            $locked->save();
            $lot->status = LotStatus::Sold;
            $lot->save();

            if ($installment) {
                if ($downCents > 0) {
                    $this->schedule($locked, 0, now()->toDateString(), $downCents);
                }
                $months = (int) $data['term_months'];
                $installmentTotal = $totalCents - $downCents;
                $each = intdiv($installmentTotal, $months);
                $remainder = $installmentTotal % $months;
                $firstDate = CarbonImmutable::parse($data['first_due_date']);
                for ($i = 1; $i <= $months; $i++) {
                    // Keep the original billing day when a month has fewer days.
                    $date = $firstDate->addMonthsNoOverflow($i - 1)->toDateString();
                    $this->schedule($locked, $i, $date, $each + ($i <= $remainder ? 1 : 0));
                }
            } else {
                $this->schedule($locked, 0, now()->toDateString(), $totalCents);
            }
        }, 3);

        $this->notifySale($transaction, 'Your sale terms have been confirmed and your payment schedule is available.', true);

        return to_route('sales.index')->with('status', 'Sale confirmed and payment schedule created. The lot is Sold; payments are recorded by the Cashier.');
    }

    /** A Cashier confirms a physical payment received outside GroveMap. */
    public function payment(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('recordPayment', $transaction);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['required', 'string', 'min:3', 'max:100'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'other'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'physically_received' => ['required', 'accepted'],
        ]);
        $amount = PesoAmount::cents($data['amount']);
        try {
            DB::transaction(function () use ($request, $transaction, $data, $amount) {
                $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('recordPayment', $locked);
                $total = PesoAmount::cents($locked->agreed_price);
                $paid = $locked->payments()->pluck('amount')->sum(fn ($value) => PesoAmount::cents($value));
                if ($amount > $total - $paid) {
                    throw ValidationException::withMessages(['amount' => 'Payment exceeds the remaining balance.']);
                }
                $reference = trim($data['reference_number']);
                if ($locked->payments()->where('reference_number', $reference)->exists()) {
                    throw ValidationException::withMessages(['reference_number' => 'This receipt/reference is already recorded for this transaction.']);
                }
                $locked->payments()->create([
                    'amount' => PesoAmount::decimal($amount),
                    'payment_date' => $data['payment_date'],
                    'reference_number' => $reference,
                    'method' => $data['method'],
                    'notes' => $data['notes'] ?? null,
                    'recorded_by_id' => $request->user()->id,
                    'verified_by_id' => $request->user()->id,
                    'verified_at' => now(),
                ]);
                if ($paid + $amount === $total) {
                    $lot = Lot::whereKey($locked->reservation->lot_id)->lockForUpdate()->firstOrFail();
                    $locked->status = 'fully_paid';
                    $locked->save();
                    $lot->status = LotStatus::FullyPaid;
                    $lot->save();
                }

                // The in-app payment alert is committed atomically with the verified receipt.
                // Previously, failures were swallowed AFTER commit, silently losing alerts.
                $reservation = $locked->reservation()->with('agent')->firstOrFail();
                $recipientIds = array_values(array_unique(array_filter([
                    $reservation->buyer_id,
                    $reservation->agent_id,
                    $reservation->agent?->unit_manager_id,
                ])));
                $remaining = $total - $paid - $amount;
                $message = sprintf(
                    'Payment of ₱%s verified (receipt %s). Remaining balance: ₱%s.',
                    number_format($amount / 100, 2),
                    $reference,
                    number_format($remaining / 100, 2)
                );
                foreach (User::whereIn('id', $recipientIds)->get() as $recipient) {
                    $recipient->notify(new SaleActivity($locked->id, $message));
                }
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reference_number' => 'This receipt/reference has already been recorded.']);
        }
        return to_route('sales.show', $transaction)->with('status', 'Physical payment recorded and verified. Remaining balance updated.');
    }

    /** In-app notifications only. Never block a committed sale/payment if an alert fails. */
    private function notifySale(Transaction $transaction, string $message, bool $includeCashiers): void
    {
        try {
            $transaction->load('reservation');
            $reservation = $transaction->reservation;
            $recipients = array_filter([
                $reservation->buyer_id,
                $reservation->agent_id,
                $reservation->agent?->unit_manager_id,
            ]);
            $users = User::whereIn('id', $recipients)->get();
            if ($includeCashiers) {
                $users = $users->merge(User::where('role', UserRole::Cashier->value)->whereNotNull('email_verified_at')->get());
            }
            foreach ($users->unique('id') as $recipient) {
                $recipient->notify(new SaleActivity($transaction->id, $message));
            }
        } catch (Throwable $exception) {
            Log::warning('GroveMap sale notification failed', [
                'transaction_id' => $transaction->id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function schedule(Transaction $transaction, int $number, string $date, int $amount): void
    {
        $transaction->paymentSchedules()->create([
            'installment_no' => $number,
            'due_date' => $date,
            'amount_due' => PesoAmount::decimal($amount),
        ]);
    }
}
