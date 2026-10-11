<x-workspace :title="auth()->user()->role === \App\Enums\UserRole::UnitManager ? 'Sale confirmations' : 'Transactions & payments'">
    <div class="my-6">
        <p class="eyebrow">GroveMap · Transactions</p>
        <h1 class="mt-2 text-2xl font-semibold text-emerald-950">{{ auth()->user()->role === \App\Enums\UserRole::UnitManager ? 'Sale confirmations' : 'Transactions & payments' }}</h1>
        <p class="mt-2 text-sm text-stone-600">{{ auth()->user()->role === \App\Enums\UserRole::UnitManager ? 'Only completed CTS records awaiting your sale confirmation are shown. Verified payment status is restricted to Buyers and Cashiers.' : 'Payment records reflect only payments confirmed as received by the Cashier. This is not an online payment gateway.' }}</p>
    </div>
    <x-feedback />
    <div class="space-y-4">
        @forelse($transactions as $transaction)
            @php($reservation = $transaction->reservation)
            <a href="{{ route('sales.show', $transaction) }}" class="block rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-emerald-300 hover:shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="eyebrow">{{ $reservation->lot->property->location }} · {{ $reservation->lot->property->name }}</p>
                        <h2 class="mt-2 font-semibold text-emerald-950">Lot {{ $reservation->lot->lot_no }} · Transaction #{{ $transaction->id }}</h2>
                        <p class="mt-2 text-sm text-stone-600">Buyer: {{ $reservation->buyer->name }} · Agent: {{ $reservation->agent->name }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-950">{{ match($transaction->status) { 'cts_processing' => 'CTS in progress', 'cts_completed' => 'Awaiting sale confirmation', 'sold' => 'Sold — balance tracking', 'fully_paid' => 'Fully Paid', default => $transaction->status } }}</span>
                </div>
                @if($transaction->agreed_price !== null)
                    <p class="mt-4 text-sm text-stone-600">Agreed price: <strong class="text-stone-900">₱{{ number_format((float) $transaction->agreed_price, 2) }}</strong> · {{ $transaction->payment_type === 'installment' ? '0% interest installment' : 'Spot cash' }}</p>
                @endif
                <span class="text-link mt-3 inline-flex text-sm">Open transaction →</span>
            </a>
        @empty
            <div class="rounded-2xl border border-stone-200 bg-white p-8 text-sm text-stone-600">{{ auth()->user()->role === \App\Enums\UserRole::UnitManager ? 'There are no completed CTS records waiting for your sale confirmation.' : 'No sales transactions have been created for your account yet.' }}</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $transactions->links() }}</div>
</x-workspace>
