<x-workspace title="Lot reservations">
    <div class="mb-7">
        <p class="eyebrow">GroveMap · Reservation tracking</p>
        <h1 class="mt-2 text-3xl font-semibold text-emerald-950">
            {{ auth()->user()->role === \App\Enums\UserRole::Buyer ? 'My reservations' : (auth()->user()->role === \App\Enums\UserRole::UnitManager ? 'Reservation reviews' : 'Reservations') }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm text-stone-600">Agents reserve lots after confirming buyer interest. Unit Managers review the buyer's submitted information. Approval moves a reservation toward CTS processing; it does not complete a sale.</p>
    </div>
    <x-feedback />
    @if($notifications->isNotEmpty())
        <section class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5" aria-label="Reservation notifications">
            <div class="flex flex-wrap justify-between gap-3">
                <h2 class="font-semibold text-emerald-950">Reservation notifications</h2>
                <form action="{{ route('reservations.notifications.read') }}" method="POST">@csrf<button class="text-link text-sm">Mark all read</button></form>
            </div>
            <ul class="mt-3 space-y-2 text-sm text-emerald-900">
                @foreach($notifications as $notification)
                    <li><a class="underline" href="{{ route('reservations.show', $notification->data['reservation_id']) }}">{{ $notification->data['description'] ?? 'Reservation updated.' }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
    <div class="space-y-4">
        @forelse($reservations as $reservation)
            <article class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="eyebrow">{{ $reservation->lot->property->location }} · Lot {{ $reservation->lot->lot_no }}</p>
                        <h2 class="mt-2 text-lg font-semibold text-emerald-950">{{ $reservation->lot->property->name }}</h2>
                        <p class="mt-2 text-sm text-stone-600">Buyer: {{ $reservation->buyer->name }} · Agent: {{ $reservation->agent->name }}</p>
                        <p class="mt-1 text-xs text-stone-500">Reserved {{ $reservation->created_at->copy()->timezone('Asia/Manila')->format('M j, Y g:i A').' PHT' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ \App\Support\ReservationProgress::badgeClass($reservation) }}">{{ \App\Support\ReservationProgress::label($reservation) }}</span>
                        <a class="btn-primary" href="{{ route('reservations.show', $reservation) }}">View reservation</a>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center">
                <h2 class="font-semibold text-emerald-950">No reservations yet</h2>
                <p class="mt-2 text-sm text-stone-500">An Agent can reserve a currently Available lot from the buyer's conversation after confirming interest.</p>
                @if(auth()->user()->role === \App\Enums\UserRole::Agent)
                    <a href="{{ route('inquiries.index') }}" class="btn-primary mt-4 inline-flex">Open inquiries</a>
                @endif
            </div>
        @endforelse
    </div>
    <div class="mt-5">{{ $reservations->links() }}</div>
</x-workspace>
