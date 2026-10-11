<x-workspace title="Conversations">
    <div class="mb-6">
        <p class="eyebrow">Communication · GroveMap</p>
        <h1 class="mt-2 text-3xl font-semibold text-emerald-950">{{ auth()->user()->role === \App\Enums\UserRole::UnitManager ? 'Inquiry assignments' : 'Conversations' }}</h1>
        <p class="mt-2 text-sm text-stone-600">
            @if(auth()->user()->role === \App\Enums\UserRole::Buyer) Conversations about lots you've selected.
            @elseif(auth()->user()->role === \App\Enums\UserRole::Agent) Inquiries from buyers assigned to you.
            @else Unassigned inquiries are shown here. Assign an Agent to the corresponding Available lot; existing conversations remain private.
            @endif
        </p>
    </div>
    <x-feedback />
    @if($notifications->isNotEmpty())
        <section class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5" aria-label="Recent notifications">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-emerald-950">Recent notifications (showing up to {{ $notifications->count() }} unread)</h2>
                <form method="POST" action="{{ route('inquiries.notifications.read') }}">@csrf<button class="text-link text-xs">Mark all read</button></form>
            </div>
            <ul class="mt-2 space-y-1 text-sm text-emerald-900">
                @foreach($notifications as $notification)
                    <li>{{ $notification->data['description'] ?? 'Inquiry update' }}</li>
                @endforeach
            </ul>
        </section>
    @endif
    <div class="space-y-3">
        @forelse($inquiries as $inquiry)
            <article class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                <div class="flex flex-wrap justify-between gap-3">
                    <div class="min-w-0">
                        <p class="eyebrow">{{ $inquiry->lot->property->location }} · Lot {{ $inquiry->lot->lot_no }}</p>
                        <h2 class="mt-1 break-words text-lg font-semibold text-emerald-950">{{ $inquiry->lot->property->name }}</h2>
                        <p class="mt-2 text-sm text-stone-600">Buyer: {{ $inquiry->buyer->name }} · Agent: {{ $inquiry->agent?->name ?? 'Awaiting assignment' }}</p>
                        <p class="mt-1 text-xs text-stone-500">Last activity: {{ $inquiry->updated_at->format('M j, Y g:i A') }}</p>
                    </div>
                    @if(auth()->user()->role !== \App\Enums\UserRole::UnitManager)
                        <div class="flex items-center gap-3">
                            @if(($unread[$inquiry->id] ?? 0) > 0)<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-900">{{ $unread[$inquiry->id] }} unread</span>@endif
                            <a href="{{ route('inquiries.show', $inquiry) }}" class="btn-primary">Open conversation</a>
                        </div>
                    @else
                        <a class="btn-secondary" href="{{ route('assignments.index') }}">Assign Agent to lot</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-12 text-center">
                <h2 class="font-semibold text-emerald-950">No inquiries yet</h2>
                <p class="mt-2 text-sm text-stone-500">@if(auth()->user()->role === \App\Enums\UserRole::Buyer) Browse available lots and select Chat with Agent to start. @elseif(auth()->user()->role === \App\Enums\UserRole::Agent) Your assigned buyer inquiries will appear here. @else When buyers inquire about unassigned lots, their inquiries will appear here. @endif</p>
                @if(auth()->user()->role === \App\Enums\UserRole::Buyer)<a class="btn-primary mt-4 inline-flex" href="{{ route('inventory.index') }}">Browse lots</a>@endif
            </div>
        @endforelse
    </div>
    <div class="mt-5">{{ $inquiries->links() }}</div>
</x-workspace>
