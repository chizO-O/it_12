<x-workspace :title="'Lot '.$lot->lot_no">
    <a class="text-link text-sm" href="{{ route('inventory.show', [$property, 'status' => 'all']) }}">← Back to lot layout</a>
    <div class="mb-6 mt-6 flex flex-wrap items-center justify-between gap-4"><h1 class="break-words text-3xl font-semibold text-emerald-950">Lot {{ $lot->lot_no }}</h1>@can('update', $lot)<a href="{{ route('properties.lots.edit', [$property, $lot]) }}" class="btn-secondary">Edit lot</a>@endcan</div>
    <div class="max-w-2xl rounded-2xl border border-stone-200 bg-white p-6 sm:p-8"><x-lot-details :property="$property" :lot="$lot" /></div>
</x-workspace>
