<x-workspace title="Operational Reports">
    <div class="my-6">
        <p class="eyebrow">GroveMap · Office reporting</p>
        <h1 class="mt-2 text-2xl font-semibold text-emerald-950">Operational Reports</h1>
        <p class="mt-2 text-sm text-stone-600">Live summaries of property availability, confirmed sale values, and verified payments. Amounts reflect recorded transactions, not forecasts.</p>
    </div>
    <x-feedback />
    @if(auth()->user()->role === \App\Enums\UserRole::UnitManager)
        <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">Reports are limited to lots and transactions handled by your supervised Agents.</p>
    @endif
    @if($showLots)
        <section class="mb-8">
            <h2 class="mb-4 text-lg font-semibold text-emerald-950">Property inventory</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach(['available' => 'Available', 'reserved' => 'Reserved', 'sold' => 'Sold', 'fully_paid' => 'Fully Paid'] as $key => $label)
                    <div class="rounded-2xl border border-stone-200 bg-white p-5">
                        <p class="text-sm text-stone-600">{{ $label }} lots</p>
                        <p class="mt-3 text-3xl font-semibold text-emerald-950">{{ number_format((int) ($lotCounts[$key] ?? 0)) }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
    @if($showSales)
        <section>
            <h2 class="mb-4 text-lg font-semibold text-emerald-950">Sales and collection summary</h2>
            <p class="mb-4 text-sm text-stone-500">Commission calculation is not included in the current GroveMap scope; these reports show actual recorded sales and collections only.</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Confirmed sales</p><p class="mt-3 text-2xl font-semibold text-emerald-950">{{ number_format($transactionsCount) }}</p></div>
                <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Agreed sales value</p><p class="mt-3 text-2xl font-semibold text-emerald-950">₱{{ number_format($totalSalesCents / 100, 2) }}</p></div>
                <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Verified collections</p><p class="mt-3 text-2xl font-semibold text-emerald-950">₱{{ number_format($totalCollectionsCents / 100, 2) }}</p></div>
                <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Remaining balance</p><p class="mt-3 text-2xl font-semibold text-emerald-950">₱{{ number_format($outstandingCents / 100, 2) }}</p></div>
            </div>
            <h3 class="mb-3 mt-8 font-semibold text-emerald-950">Verified payment collections — last six months</h3>
            <div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white">
                <table class="min-w-full divide-y divide-stone-100 text-left text-sm">
                    <thead class="bg-stone-50 text-stone-600"><tr><th class="p-4">Month</th><th class="p-4 text-right">Amount received</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach($monthly as $month)
                            <tr><td class="p-4">{{ $month['label'] }}</td><td class="p-4 text-right font-semibold">₱{{ number_format($month['amount'] / 100, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-stone-500">Collections use the payment receipt date and include only amounts recorded as verified by the Cashier. GroveMap does not process money online.</p>
        </section>
    @endif
</x-workspace>
