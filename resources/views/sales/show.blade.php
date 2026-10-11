@php
    $reservation = $transaction->reservation;
    $confirmed = (bool) $transaction->terms_confirmed_at;
    $currency = fn ($cents) => '₱'.number_format($cents / 100, 2);
    $remainingForSchedules = $paidCents;
@endphp
<x-workspace :title="'Payment tracking — Lot '.$reservation->lot->lot_no">
    <a href="{{ route('sales.index') }}" class="text-link text-sm">← Back to sales and payments</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">{{ $reservation->lot->property->location }} · {{ $reservation->lot->property->name }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-emerald-950">Sales transaction — Lot {{ $reservation->lot->lot_no }}</h1>
            <p class="mt-2 text-sm text-stone-600">Buyer: {{ $reservation->buyer->name }} · Agent: {{ $reservation->agent->name }}</p>
        </div>
        <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900">{{ match($transaction->status) { 'cts_processing' => 'CTS processing', 'cts_completed' => 'Awaiting sale confirmation', 'sold' => 'Sold', 'fully_paid' => 'Fully Paid', default => $transaction->status } }}</span>
    </div>
    <x-feedback />
    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-xs font-medium text-stone-500">Agreed selling price</p><p class="mt-2 text-xl font-semibold text-emerald-950">{{ $confirmed ? $currency($totalCents) : 'Awaiting agreement' }}</p></div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-xs font-medium text-stone-500">Verified payments received</p><p class="mt-2 text-xl font-semibold text-emerald-950">{{ $currency($paidCents) }}</p></div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-xs font-medium text-stone-500">Remaining balance</p><p class="mt-2 text-xl font-semibold text-emerald-950">{{ $confirmed ? $currency($balanceCents) : 'Not available yet' }}</p></div>
    </section>
    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Sales agreement</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-stone-500">CTS status</dt><dd class="text-right">{{ $transaction->ctsRecord?->status?->label() ?? 'Not created' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Payment terms</dt><dd class="text-right">{{ $confirmed ? ($transaction->payment_type === 'installment' ? 'Installment · 0% interest · '.$transaction->term_months.' months' : 'Spot cash') : 'Not confirmed' }}</dd></div>
                @if($confirmed)<div class="flex justify-between gap-3"><dt class="text-stone-500">Agreed down payment</dt><dd>{{ $currency(\App\Support\PesoAmount::cents($transaction->down_payment ?? '0')) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Sale confirmed</dt><dd>{{ $transaction->terms_confirmed_at->format('M j, Y') }}</dd></div>@endif
            </dl>
            @if(auth()->user()->role !== \App\Enums\UserRole::Cashier && auth()->user()->role !== \App\Enums\UserRole::FieldPlanner)
                <a class="text-link mt-5 inline-flex" href="{{ route('cts.show', $transaction->ctsRecord) }}">View CTS tracking →</a>
            @endif
            <p class="mt-4 text-xs leading-5 text-stone-500">Agent payment preferences are not final terms. Only the responsible Unit Manager can confirm the actual externally agreed sale.</p>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Payment process</h2>
            <p class="mt-3 text-sm leading-6 text-stone-600">Payment is made to SGRE outside the application. The Cashier records the amount only after confirming physical receipt or bank transfer.</p>
            @if(!$confirmed)
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Sale confirmation and the first payment schedule are pending CTS completion and Unit Manager confirmation.</p>
            @elseif($transaction->status === 'fully_paid')
                <p class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">The transaction is Fully Paid. The corresponding lot is marked Fully Paid.</p>
            @else
                <p class="mt-4 rounded-lg bg-stone-50 p-3 text-sm">Balance updates automatically after every verified Cashier receipt.</p>
            @endif
        </section>
    </div>
    @can('confirmTerms', $transaction)
        <section class="mt-6 rounded-2xl border border-emerald-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Unit Manager — confirm sale and final payment terms</h2>
            <p class="mt-2 text-sm text-stone-600">Complete only after the signed/notarized CTS has been recorded and the actual agreed selling price and payment terms are confirmed outside GroveMap. This marks the lot Sold; not Fully Paid.</p>
            <form method="POST" action="{{ route('sales.confirm', $transaction) }}" class="mt-5 space-y-4" data-sales-terms-form>
                @csrf
                <x-form-errors />
                <x-form-field name="agreed_price" label="Final agreed selling price (PHP)" type="number" min="0.01" max="999999999999.99" step="0.01" :value="old('agreed_price', $reservation->lot->selling_price)" />
                <x-select-field name="payment_type" label="Final agreed payment method" data-sales-payment-type>
                    <option value="spot_cash" @selected(old('payment_type') === 'spot_cash')>Spot cash</option>
                    <option value="installment" @selected(old('payment_type') === 'installment')>0% interest installment</option>
                </x-select-field>
                <fieldset data-sales-installment-fields @if(old('payment_type') !== 'installment') hidden disabled @endif class="grid gap-4 sm:grid-cols-3">
                    <x-form-field name="down_payment" label="Agreed down payment (PHP)" type="number" min="0" step="0.01" :required="false" :value="old('down_payment', '0.00')" />
                    <x-form-field name="term_months" label="Duration in months" type="number" min="1" max="120" step="1" :required="false" />
                    <x-form-field name="first_due_date" label="First monthly due date" type="date" :required="false" :value="old('first_due_date', now()->addMonthNoOverflow()->toDateString())" min="{{ now()->toDateString() }}" />
                </fieldset>
                <label class="flex gap-3 text-sm"><input type="checkbox" name="terms_confirmed" value="1" required class="mt-1"><span>I confirm the externally agreed sale and payment terms. The lot will become Sold.</span></label>
                <button class="btn-primary">Confirm Sale & Generate Payment Schedule</button>
            </form>
        </section>
    @endcan
    @if($confirmed)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Payment schedule</h2>
            <p class="mt-2 text-xs text-stone-500">Schedule amounts are calculated at 0% interest and apply verified payments to the earliest installment first. Unverified or unreceived payments do not count.</p>
            <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-stone-200 text-stone-500"><th class="py-3 pr-3">Payment</th><th class="py-3 pr-3">Due date</th><th class="py-3 pr-3">Amount</th><th class="py-3">Status</th></tr></thead><tbody>
                @foreach($transaction->paymentSchedules as $schedule)
                    @php
                        $due = \App\Support\PesoAmount::cents($schedule->amount_due);
                        $covered = min($remainingForSchedules, $due);
                        $remainingForSchedules = max(0, $remainingForSchedules - $due);
                        $status = $covered >= $due ? 'Paid' : ($schedule->due_date->isBefore(today()) ? 'Overdue' : ($covered > 0 ? 'Partially paid' : 'Pending'));
                    @endphp
                    <tr class="border-b border-stone-100"><td class="py-3 pr-3">{{ $schedule->installment_no === 0 ? ($transaction->payment_type === 'spot_cash' ? 'Spot cash' : 'Down payment') : 'Month '.$schedule->installment_no }}</td><td class="py-3 pr-3">{{ $schedule->due_date->format('M j, Y') }}</td><td class="py-3 pr-3">{{ $currency($due) }}</td><td class="py-3">{{ $status }}</td></tr>
                @endforeach
            </tbody></table></div>
        </section>
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Verified payment history</h2>
            <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-stone-200 text-stone-500"><th class="py-3 pr-3">Received</th><th class="py-3 pr-3">Amount</th><th class="py-3 pr-3">Method</th><th class="py-3 pr-3">Reference</th><th class="py-3">Cashier</th></tr></thead><tbody>
                @forelse($transaction->payments as $payment)
                    <tr class="border-b border-stone-100"><td class="py-3 pr-3">{{ $payment->payment_date->format('M j, Y') }}</td><td class="py-3 pr-3">{{ $currency(\App\Support\PesoAmount::cents($payment->amount)) }}</td><td class="py-3 pr-3">{{ str_replace('_', ' ', ucfirst($payment->method)) }}</td><td class="py-3 pr-3 break-all">{{ $payment->reference_number }}</td><td class="py-3">{{ $payment->recorder->name }}</td></tr>
                @empty<tr><td class="py-4 text-stone-500" colspan="5">No verified payments have been recorded.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    @endif
    @can('recordPayment', $transaction)
        <section class="mt-6 rounded-2xl border border-emerald-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Cashier — record verified payment</h2>
            <p class="mt-2 text-sm text-stone-600">Record only an amount that SGRE actually received. A unique receipt/reference is required. The amount cannot exceed the balance.</p>
            <form method="POST" action="{{ route('sales.payments.store', $transaction) }}" class="mt-5 space-y-4">
                @csrf
                <x-form-errors />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form-field name="amount" label="Amount received (PHP)" type="number" min="0.01" step="0.01" max="999999999999.99" />
                    <x-form-field name="payment_date" label="Date payment received" type="date" :value="old('payment_date', now()->toDateString())" max="{{ now()->toDateString() }}" />
                    <x-form-field name="reference_number" label="Receipt / transaction reference" maxlength="100" />
                    <x-select-field name="method" label="How was payment received?">
                        <option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="other">Other verified method</option>
                    </x-select-field>
                </div>
                <x-textarea-field name="notes" label="Receipt notes (optional)" :required="false" rows="2" maxlength="1000" />
                <label class="flex gap-3 text-sm"><input type="checkbox" name="physically_received" value="1" required class="mt-1"><span>I personally verified that SGRE received this payment and the reference is accurate.</span></label>
                <button class="btn-primary">Record & Verify Payment</button>
            </form>
        </section>
    @endcan
</x-workspace>
