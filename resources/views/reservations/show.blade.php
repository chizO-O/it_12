<x-workspace :title="'Reservation for Lot '.$reservation->lot->lot_no">
    <a href="{{ route('reservations.index') }}" class="text-link text-sm">← Back to reservations</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">{{ $reservation->lot->property->location }} · {{ $reservation->lot->property->name }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-emerald-950">Reservation for Lot {{ $reservation->lot->lot_no }}</h1>
        </div>
        <span class="rounded-full px-4 py-2 text-sm font-semibold {{ $reservation->status->isActive() ? 'bg-amber-100 text-amber-900' : 'bg-stone-100 text-stone-700' }}">{{ $reservation->status->label() }}</span>
    </div>
    <x-feedback />
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Property & Reservation</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Buyer</dt><dd class="text-right">{{ $reservation->buyer->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Agent</dt><dd class="text-right">{{ $reservation->agent->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Area</dt><dd class="text-right">{{ $reservation->lot->property->location }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Lot / Block</dt><dd class="text-right">{{ $reservation->lot->lot_no }} / {{ $reservation->lot->block_no ?: 'None' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Area (sqm)</dt><dd class="text-right">{{ number_format((float) $reservation->lot->area_sqm, 2) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Listed price</dt><dd class="text-right">₱{{ number_format((float) $reservation->lot->selling_price, 2) }}</dd></div>
            </dl>
            @if($reservation->agent_notes && auth()->user()->role !== \App\Enums\UserRole::Buyer)
                <p class="mt-4 rounded-lg bg-stone-50 p-3 text-sm text-stone-700"><strong>Agent's note:</strong> {{ $reservation->agent_notes }}</p>
            @endif
            @if($reservation->reviewed_at)
                <div class="mt-4 rounded-lg bg-stone-50 p-3 text-sm">
                    <p>Reviewed by {{ $reservation->reviewer?->name ?? 'Authorized Unit Manager' }} on {{ $reservation->reviewed_at->format('M j, Y') }}</p>
                    @if($reservation->review_notes)<p class="mt-2 whitespace-pre-wrap">{{ $reservation->review_notes }}</p>@endif
                </div>
            @endif
            @if(in_array(auth()->user()->role, [\App\Enums\UserRole::Buyer, \App\Enums\UserRole::Agent], true))
                <a class="text-link mt-5 inline-flex" href="{{ route('inquiries.show', $reservation->inquiry) }}">Return to buyer-agent conversation →</a>
            @endif
        </section>
        @include('reservations.contact')
    </div>
    @include('reservations.assessment')
    @include('reservations.corrections')
    @if(auth()->user()->role === \App\Enums\UserRole::UnitManager && $reservation->status === \App\Enums\ReservationStatus::Pending)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Unit Manager review</h2>
            <p class="mt-2 text-sm text-stone-600">Review the buyer details and terms outside the application. Approval keeps the lot Reserved for the upcoming CTS workflow; it does not mark the lot Sold.</p>
            <form method="POST" action="{{ route('reservations.review', $reservation) }}" class="mt-4 space-y-3">
                @csrf @method('PATCH')
                <label for="review_notes" class="block text-sm font-medium">Review notes (required when rejecting)</label>
                <textarea class="form-input w-full" name="review_notes" id="review_notes" rows="3" maxlength="2000">{{ old('review_notes') }}</textarea>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="decision" value="approve" class="btn-primary" @disabled(!$reservation->buyerForm)>Approve for CTS</button>
                    <button type="submit" name="decision" value="reject" class="btn-secondary">Reject reservation</button>
                </div>
            </form>
        </section>
    @endif
    @if($reservation->status->isActive() && (
        (auth()->user()->role === \App\Enums\UserRole::Agent && $reservation->agent_id === auth()->id() && $reservation->status === \App\Enums\ReservationStatus::Pending)
        || auth()->user()->role === \App\Enums\UserRole::UnitManager
    ))
        <section class="mt-6 rounded-2xl border border-red-100 bg-white p-6">
            <h2 class="font-semibold text-red-900">Cancel reservation</h2>
            <p class="mt-2 text-sm text-stone-600">Cancel only when the buyer withdraws or the reservation must be released. The lot will return to Available.</p>
            <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" class="mt-3 flex flex-col gap-3">
                @csrf @method('PATCH')
                <label for="cancel_reason" class="text-sm font-medium">Reason</label>
                <textarea id="cancel_reason" name="reason" class="form-input w-full" rows="2" maxlength="2000" required></textarea>
                <button type="submit" class="btn-secondary self-start" onclick="return confirm('Cancel this reservation and release the lot?')">Cancel reservation</button>
            </form>
        </section>
    @endif
    @if($reservation->status === \App\Enums\ReservationStatus::Approved)
        <section class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
            <h2 class="font-semibold">CTS document tracking</h2>
            <p class="mt-2">Reservation approved. The lot remains Reserved while the CTS is processed externally.</p>
            @if($reservation->transaction?->ctsRecord)
                <a href="{{ route('cts.show', $reservation->transaction->ctsRecord) }}" class="btn-primary mt-4 inline-flex">View CTS progress</a>
            @elseif(auth()->user()->role === \App\Enums\UserRole::UnitManager && $reservation->agent->unit_manager_id === auth()->id())
                <form method="POST" action="{{ route('cts.initiate', $reservation) }}" class="mt-4">@csrf
                    <button type="submit" class="btn-primary">Initiate CTS tracking</button>
                </form>
            @else
                <p class="mt-2">Awaiting Unit Manager initiation.</p>
            @endif
        </section>
    @endif
</x-workspace>
