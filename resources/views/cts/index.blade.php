<x-workspace title="CTS (Contract to Sell)">
    <div class="mb-7">
        <p class="eyebrow">GroveMap · Contracts</p>
        <h1 class="mt-2 text-3xl font-semibold text-emerald-950">CTS (Contract to Sell)</h1>
        @if(auth()->user()->role === \App\Enums\UserRole::Buyer)
            <p class="mt-2 text-sm text-stone-600">Your Contract to Sell documents for each lot. Final signed contracts become available here when the Secretary records completion. Drafts requiring your review are also shown.</p>
        @else
            <p class="mt-2 text-sm text-stone-600">Prepare and access the Contract to Sell for each approved reservation. The legal document is prepared, signed and notarized outside GroveMap.</p>
        @endif
    </div>
    <x-feedback />

    @if(auth()->user()->role === \App\Enums\UserRole::Admin && $readyReservations->isNotEmpty())
        <section class="mb-7 rounded-2xl border border-emerald-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-emerald-950">Approved reservations — prepare CTS</h2>
            <p class="mt-2 text-sm text-stone-600">You only need to choose the approved buyer/reservation. The buyer's name and lot are filled in automatically; no additional Unit Manager initiation is needed.</p>
            <div class="mt-4 space-y-3">
                @foreach($readyReservations as $reservation)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 p-4">
                        <div>
                            <p class="font-medium text-emerald-950">{{ $reservation->buyer->name }} — Lot {{ $reservation->lot->lot_no }}</p>
                            <p class="mt-1 text-xs text-stone-600">{{ $reservation->lot->property->name }} · {{ $reservation->lot->property->location }}</p>
                        </div>
                        <form method="POST" action="{{ route('cts.initiate', $reservation) }}">
                            @csrf
                            <button class="btn-primary" type="submit">Prepare CTS</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <h2 class="mb-4 text-lg font-semibold text-emerald-950">{{ auth()->user()->role === \App\Enums\UserRole::Buyer ? 'My CTS documents' : 'CTS records' }}</h2>
        <div class="space-y-3">
            @forelse($records as $cts)
                @php
                    $reservation = $cts->transaction->reservation;
                    $isCompleted = $cts->status === \App\Enums\CtsStatus::Completed;
                @endphp
                <article class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-5">
                    <div>
                        <p class="eyebrow">{{ $reservation->lot->property->location }} · {{ $reservation->lot->property->name }}</p>
                        <h3 class="mt-1 text-lg font-semibold text-emerald-950">Lot {{ $reservation->lot->lot_no }} — Contract to Sell</h3>
                        <p class="mt-1 text-sm text-stone-600">Buyer: {{ $reservation->buyer->name }}</p>
                        <span class="mt-2 inline-block rounded-full {{ $isCompleted ? 'bg-emerald-100' : 'bg-amber-50' }} px-3 py-1 text-xs font-medium text-emerald-900">{{ $isCompleted ? 'Final CTS available' : $cts->status->label() }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($isCompleted && $cts->executed_path)
                            <a href="{{ route('cts.document', [$cts, 'executed']) }}" class="btn-secondary" target="_blank" rel="noopener noreferrer">Open final CTS PDF</a>
                        @endif
                        <a class="btn-primary" href="{{ route('cts.show', $cts) }}">{{ $isCompleted ? 'View Contract to Sell' : 'View CTS details' }}</a>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center">
                    <h3 class="font-semibold text-emerald-950">No CTS documents yet</h3>
                    <p class="mt-2 text-sm text-stone-500">{{ auth()->user()->role === \App\Enums\UserRole::Buyer ? 'Your Contract to Sell record will appear after the office opens it for your approved lot. Complete your Buyer CTS details before the Secretary finalizes the document.' : 'The Secretary/Admin can prepare a CTS for an approved reservation.' }}</p>
                </div>
            @endforelse
        </div>
        <div class="mt-5">{{ $records->links() }}</div>
    </section>
</x-workspace>
