<x-workspace title="Browse lots">
    <p class="eyebrow">Property inventory</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-emerald-950">Find your next place</h1><p class="mb-7 mt-3 max-w-xl text-sm leading-6 text-stone-600">Explore recorded properties in Toril, Kidapawan, and Kapalong. Choose a property to see its lot details and current availability.</p>
    <x-feedback />
    <form method="GET" action="{{ route('inventory.index') }}" class="mb-7 flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white p-5">
        <x-select-field name="location" label="Location" class="min-w-40">
            <option value="">All locations</option>
            @foreach($locations as $location)<option value="{{ $location->value }}" @selected(request('location') === $location->value)>{{ $location->value }}</option>@endforeach
        </x-select-field>
        <x-select-field name="availability" label="Properties to show">
            <option value="available" @selected($availability === 'available')>With available lots</option><option value="all" @selected($availability === 'all')>All properties</option>
        </x-select-field>
        <button class="btn-primary">Find properties</button><a class="text-link px-2 py-3 text-sm" href="{{ route('inventory.index') }}">Clear</a>
    </form>
    <div class="mb-5 flex items-center justify-between gap-4"><h2 class="text-lg font-semibold text-emerald-950">{{ request('location') ?: 'All locations' }}</h2><p class="text-sm text-stone-500">{{ $properties->total() }} {{ Str::plural('property', $properties->total()) }}</p></div>
    <div class="grid gap-5 sm:grid-cols-2">
        @forelse($properties as $property)
            <article class="min-w-0 overflow-hidden rounded-2xl border border-stone-200 bg-white">
                <div class="flex items-center justify-between gap-3 bg-emerald-50 px-6 py-5"><span class="eyebrow">{{ $property->location }}</span><span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-900">{{ $property->available_lots_count }} available</span></div>
                <div class="p-6"><h3 class="break-words text-xl font-semibold text-emerald-950"><a href="{{ route('inventory.show', [$property, 'status' => $availability === 'all' ? 'all' : 'available']) }}" class="text-link">{{ $property->name }}</a></h3><p class="mt-2 break-words text-sm text-stone-500">{{ $property->address ?? 'Address not recorded' }}</p>
                    @if($property->description)<p class="mt-4 break-words text-sm leading-6 text-stone-600">{{ Str::limit($property->description, 180) }}</p>@endif
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 pt-4"><span class="text-sm text-stone-500">{{ $property->lots_count }} lots recorded</span><a class="text-link text-sm" href="{{ route('inventory.show', [$property, 'status' => $availability === 'all' ? 'all' : 'available']) }}">Explore lots →</a></div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-stone-300 p-10 text-center sm:col-span-2"><h3 class="font-semibold text-emerald-950">No matching properties</h3><p class="mt-2 text-sm leading-6 text-stone-500">Try another location or choose All properties to include those without available lots.</p></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $properties->links() }}</div>
</x-workspace>
