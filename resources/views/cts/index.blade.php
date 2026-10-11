<x-workspace title="CTS tracking">
    <div class="mb-7">
        <p class="eyebrow">GroveMap · Contract to Sell</p>
        <h1 class="mt-2 text-3xl font-semibold text-emerald-950">CTS tracking</h1>
        <p class="mt-2 text-sm text-stone-600">Track preparation, review, signing and notarization of externally prepared CTS documents. This application does not execute legal contracts.</p>
    </div>
    <x-feedback />
    <div class="space-y-3">
        @forelse($records as $cts)
            @php($reservation = $cts->transaction->reservation)
            <article class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-5">
                <div>
                    <p class="eyebrow">{{ $reservation->lot->property->location }} · {{ $reservation->lot->property->name }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-emerald-950">Lot {{ $reservation->lot->lot_no }} — CTS #{{ $cts->id }}</h2>
                    <p class="mt-1 text-sm text-stone-600">Buyer: {{ $reservation->buyer->name }}</p>
                    <span class="mt-2 inline-block rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-900">{{ $cts->status->label() }}</span>
                </div>
                <a class="btn-primary" href="{{ route('cts.show', $cts) }}">View CTS</a>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center">
                <h2 class="font-semibold text-emerald-950">No CTS records yet</h2>
                <p class="mt-2 text-sm text-stone-500">A Unit Manager can begin CTS tracking from an approved reservation.</p>
                @if(auth()->user()->role === \App\Enums\UserRole::UnitManager)
                    <a href="{{ route('reservations.index') }}" class="btn-primary mt-4 inline-flex">Open approved reservations</a>
                @endif
            </div>
        @endforelse
    </div>
    <div class="mt-5">{{ $records->links() }}</div>
</x-workspace>
