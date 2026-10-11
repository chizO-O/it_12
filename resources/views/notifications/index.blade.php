<x-workspace title="Notifications">
    <div class="my-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="eyebrow">GroveMap · Activity</p>
            <h1 class="mt-2 text-2xl font-semibold text-emerald-950">Notifications</h1>
            <p class="mt-2 text-sm text-stone-600">Conversations, reservations, CTS updates, verified payment receipts, and reminders in one place.</p>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn-secondary">Mark all as read ({{ $unreadCount }})</button>
            </form>
        @endif
    </div>
    <x-feedback />
    <div class="space-y-3">
        @forelse($notifications as $item)
            <article class="rounded-2xl border p-5 {{ $item->read_at ? 'border-stone-200 bg-white' : 'border-emerald-300 bg-emerald-50/60' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-800">{{ $item->read_at ? 'Read' : 'New' }} · {{ match (class_basename($item->type)) { 'InquiryActivity' => 'Conversations', 'ReservationActivity' => 'Reservations', 'CtsActivity' => 'Contract to Sell', 'SaleActivity' => 'Sales & Payments', 'PaymentReminder' => 'Payment reminder', default => 'Activity' } }}</p>
                        <p class="mt-2 break-words text-sm text-stone-800">{{ $item->data['description'] ?? 'You have an account update.' }}</p>
                        <p class="mt-2 text-xs text-stone-500">{{ $item->created_at->copy()->timezone('Asia/Manila')->format('M j, Y g:i A').' PHT' }}</p>
                    </div>
                    <form method="POST" action="{{ route('notifications.open', $item->id) }}">
                        @csrf
                        <button type="submit" class="btn-secondary">View details</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-stone-200 bg-white p-8 text-sm text-stone-600">No notifications yet. Updates will appear here as you use GroveMap.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $notifications->links() }}</div>
</x-workspace>
