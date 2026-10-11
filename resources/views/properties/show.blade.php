<x-workspace :title="$property->name">
    <a href="{{ route('properties.index') }}" class="text-link text-sm">← All properties</a>
    <div class="mb-7 mt-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0"><p class="eyebrow">{{ $property->location }}</p><h1 class="mt-2 break-words text-3xl font-semibold text-emerald-950">{{ $property->name }}</h1><p class="mt-2 break-words text-sm text-stone-500">{{ $property->address ?? 'Address not recorded' }}</p></div>
        <a class="btn-secondary" href="{{ route('properties.edit', $property) }}">Edit property</a>
    </div>
    <x-feedback />
    <div class="mb-6 rounded-2xl border border-stone-200 bg-white p-6">
        <dl class="flex flex-wrap gap-x-10 gap-y-4 text-sm"><div><dt class="text-stone-500">Total area</dt><dd class="mt-1 font-semibold">{{ $property->total_area_sqm !== null ? number_format((float) $property->total_area_sqm, 2).' m²' : 'Not recorded' }}</dd></div><div><dt class="text-stone-500">Recorded lots</dt><dd class="mt-1 font-semibold">{{ $property->lots_count }}</dd></div></dl>
        @if($property->description)<p class="mt-5 whitespace-pre-line break-words text-sm leading-6 text-stone-600">{{ $property->description }}</p>@endif
    </div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold text-emerald-950">Property layout</h2><div class="flex flex-wrap gap-3"><a class="btn-primary" href="{{ route('properties.lots.create', $property) }}">Add lot</a></div></div>
    <x-lot-layout :property="$property" :lots="$lots" :grid="$grid" layout-id="management-layout" />
    <h2 class="mb-4 mt-8 text-xl font-semibold text-emerald-950">Lot records</h2>
    <div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm"><caption class="sr-only">Lots belonging to {{ $property->name }}</caption>
            <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500"><tr><th class="px-5 py-4">Lot / block</th><th class="px-5 py-4">Area</th><th class="px-5 py-4">Price</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($lots as $lot)
                    <tr><td class="max-w-60 break-words px-5 py-4"><span class="font-medium text-emerald-950">{{ $lot->lot_no }}</span><p class="mt-1 text-xs text-stone-500">Block {{ $lot->block_no ?? '—' }}</p></td><td class="whitespace-nowrap px-5 py-4">{{ number_format((float) $lot->area_sqm, 2) }} m²</td><td class="whitespace-nowrap px-5 py-4">₱{{ number_format((float) $lot->selling_price, 2) }}</td><td class="whitespace-nowrap px-5 py-4"><x-lot-status :status="$lot->status" /></td>
                        <td class="px-5 py-4">@can('update', $lot)<div class="flex items-center gap-4"><a class="text-link" href="{{ route('properties.lots.edit', [$property, $lot]) }}">Edit</a><form method="POST" action="{{ route('properties.lots.destroy', [$property, $lot]) }}" data-confirm="Delete this available lot? This cannot be undone.">@csrf @method('DELETE')<button class="min-h-11 font-semibold text-red-700 underline-offset-4 hover:underline">Delete</button></form></div>@else<span class="text-xs text-stone-500">Protected record</span>@endcan</td>
                    </tr>
                @empty<tr><td colspan="5" class="px-5 py-10 text-center text-stone-500">No lots recorded. Add an authorized lot to begin.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs leading-5 text-stone-500">Reserved, Sold, and Fully Paid lot records are protected from inventory editing and deletion.</p>
    <div class="mt-6">{{ $lots->links() }}</div>
    <x-lot-dialogs :property="$property" :lots="$lots" />
    <div class="mt-10 rounded-2xl border border-stone-200 p-5">
        @if($property->lots_count === 0)<form method="POST" action="{{ route('properties.destroy', $property) }}" data-confirm="Delete this empty property? This cannot be undone.">@csrf @method('DELETE')<button class="min-h-11 font-semibold text-red-700">Delete empty property</button></form>@else<p class="text-sm text-stone-500">Properties containing lots cannot be deleted.</p>@endif
    </div>
</x-workspace>
