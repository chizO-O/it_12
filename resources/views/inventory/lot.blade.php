<x-workspace :title="'Lot '.$lot->lot_no">
    <a class="text-link text-sm" href="{{ route('inventory.show', [$property, 'status' => 'all']) }}">← Back to lot layout</a>
    <div class="mb-6 mt-6 flex flex-wrap items-center justify-between gap-4"><h1 class="break-words text-3xl font-semibold text-emerald-950">Lot {{ $lot->lot_no }}</h1>@can('update', $lot)<a href="{{ route('properties.lots.edit', [$property, $lot]) }}" class="btn-secondary">Edit lot</a>@endcan</div>
    <div class="max-w-2xl rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
        <x-lot-details :property="$property" :lot="$lot" />
        @if(auth()->user()->role === \App\Enums\UserRole::Buyer && $lot->status === \App\Enums\LotStatus::Available)
            <form action="{{ route('inquiries.store', [$property, $lot]) }}" method="POST" class="mt-7 space-y-3 border-t border-stone-100 pt-6">
                @csrf
                <label for="message" class="block text-sm font-semibold">Contact the assigned Agent about this lot</label>
                <textarea class="form-input w-full" name="message" id="message" required rows="3" maxlength="2000">{{ old('message', "I'd like to learn more about Lot ".$lot->lot_no.".") }}</textarea>
                @error('message')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <button class="btn-primary" type="submit">Chat with Agent</button>
            </form>
        @endif
    </div>
</x-workspace>
