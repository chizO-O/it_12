<x-workspace :title="'Inquiry for Lot '.$inquiry->lot->lot_no">
    <a href="{{ route('inquiries.index') }}" class="text-link text-sm">← Back to conversations</a>
    <div class="my-6">
        <p class="eyebrow">{{ $inquiry->lot->property->location }} · {{ $inquiry->lot->property->name }}</p>
        <h1 class="mt-2 break-words text-2xl font-semibold text-emerald-950">Conversation about Lot {{ $inquiry->lot->lot_no }}</h1>
        <p class="mt-2 text-sm text-stone-600">Buyer: {{ $inquiry->buyer->name }} · Agent: {{ $inquiry->agent?->name ?? 'Awaiting assignment' }}</p>
    </div>
    <x-feedback />
    @if($latestReservation)
        <section class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-semibold text-emerald-950">Lot reservation: {{ $latestReservation->status->label() }}</h2>
            <p class="mt-1 text-sm text-stone-600">Reservation details and buyer information are handled separately from this conversation.</p>
            <a class="text-link mt-3 inline-flex" href="{{ route('reservations.show', $latestReservation) }}">View reservation →</a>
        </section>
    @endif
    @if(!$activeReservation && auth()->user()->role === \App\Enums\UserRole::Agent && $inquiry->lot->status === \App\Enums\LotStatus::Available && $inquiry->lot->assigned_agent_id === auth()->id())
        <section class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <h2 class="font-semibold text-emerald-950">Buyer interested in this lot?</h2>
            <p class="mt-1 text-sm text-stone-600">Reserve only after the buyer confirms genuine interest. The lot will be held and the Unit Manager notified.</p>
            <form method="POST" action="{{ route('reservations.store', $inquiry) }}" class="mt-4 space-y-3">
                @csrf
                <label for="agent_notes" class="block text-sm font-medium">Agent notes (optional)</label>
                <textarea id="agent_notes" name="agent_notes" rows="2" maxlength="2000" class="form-input w-full" placeholder="Summary of the buyer's interest">{{ old('agent_notes') }}</textarea>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" class="mt-1" name="confirmed_interest" value="1" required> <span>I confirm that this buyer has expressed genuine interest in purchasing this lot.</span></label>
                <button type="submit" class="btn-primary">Reserve lot &amp; inform Unit Manager</button>
            </form>
        </section>
    @endif
    <div class="rounded-2xl border border-stone-200 bg-white">
        <div class="border-b border-stone-100 px-5 py-4 text-sm text-stone-500">Messages stay attached to this specific property inquiry. Never send passwords or banking credentials through chat.</div>
        <div id="conversation-messages" data-conversation-updates="{{ route('inquiries.updates', $inquiry) }}" data-last-id="{{ $messages->first()?->id ?? 0 }}" class="max-h-[55vh] min-h-64 space-y-4 overflow-y-auto p-5" aria-label="Conversation history" aria-live="polite">
            @foreach($messages->getCollection()->reverse() as $message)
                <article class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}" data-message-id="{{ $message->id }}">
                    <div class="max-w-[88%] break-words rounded-2xl px-4 py-3 text-sm {{ $message->sender_id === auth()->id() ? 'bg-emerald-900 text-white' : 'bg-stone-100 text-stone-800' }}">
                        <p class="text-xs font-semibold opacity-70">{{ $message->sender->name }} · {{ $message->created_at->copy()->timezone('Asia/Manila')->format('M j, g:i A').' PHT' }}</p>
                        <p class="mt-2 whitespace-pre-wrap">{{ $message->body }}</p>
                    </div>
                </article>
            @endforeach
        </div>
        @if($messages->hasMorePages())
            <div class="border-t border-stone-100 px-5 py-3 text-sm">{{ $messages->links() }}</div>
        @endif
        @if(!$inquiry->agent_id && auth()->user()->role === \App\Enums\UserRole::Buyer)
            <p class="border-t border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-900">An Agent has not yet been assigned. Your message is saved; the Unit Manager can assign an Agent to this lot.</p>
        @endif
        <form method="POST" action="{{ route('inquiries.messages.store', $inquiry) }}" class="border-t border-stone-200 p-5">
            @csrf
            <label for="body" class="mb-2 block text-sm font-semibold">Reply</label>
            <textarea id="body" name="body" rows="3" maxlength="2000" class="form-input w-full" required placeholder="Type your message about this lot...">{{ old('body') }}</textarea>
            @error('body')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs text-stone-500">New messages refresh every 10 seconds while this page is open.</span>
                <button type="submit" class="btn-primary">Send message</button>
            </div>
        </form>
    </div>
</x-workspace>
