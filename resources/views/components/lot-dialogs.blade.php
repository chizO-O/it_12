@props(['property', 'lots'])
@foreach($lots as $lot)
    <dialog id="lot-details-{{ $lot->id }}" data-lot-id="{{ $lot->id }}" data-property-id="{{ $property->id }}" aria-labelledby="lot-heading-{{ $lot->id }}" class="lot-dialog m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border-0 bg-white p-6 text-stone-800 shadow-xl sm:p-8">
        <div class="mb-6 flex items-start justify-between gap-3">
            <h2 id="lot-heading-{{ $lot->id }}" class="break-words text-2xl font-semibold text-emerald-950">Lot {{ $lot->lot_no }}</h2>
            <button type="button" data-close-dialog class="btn-secondary shrink-0 px-3" aria-label="Close lot details" autofocus>✕</button>
        </div>
        <x-lot-details :property="$property" :lot="$lot" />
        <div class="mt-6 flex flex-wrap gap-3">
            @can('update', $lot)<a class="btn-primary" href="{{ route('properties.lots.edit', [$property, $lot]) }}">Edit lot</a>@endcan
            @if(auth()->user()->role === \App\Enums\UserRole::Buyer && $lot->status === \App\Enums\LotStatus::Available)
                <form method="POST" action="{{ route('inquiries.store', [$property, $lot]) }}" class="w-full rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                    @csrf
                    <label for="inquiry-start-{{ $lot->id }}" class="mb-2 block text-sm font-semibold text-emerald-950">Contact the assigned Agent about this lot</label>
                    <textarea id="inquiry-start-{{ $lot->id }}" name="message" rows="2" maxlength="2000" required class="form-input w-full" placeholder="Hi, I would like more details about this lot.">I'd like to learn more about Lot {{ $lot->lot_no }}.</textarea>
                    <button class="btn-primary mt-3" type="submit">Chat with Agent</button>
                </form>
            @endif
            <a class="btn-secondary" href="{{ route('inventory.lot', [$property, $lot]) }}">Open details page</a>
            <button type="button" data-close-dialog class="btn-secondary">Done</button>
        </div>
    </dialog>
@endforeach
