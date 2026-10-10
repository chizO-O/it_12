<x-workspace title="Properties & lots">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div><p class="eyebrow">Field Planner</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-emerald-950">Properties & lots</h1><p class="mt-2 text-sm text-stone-600">Maintain authorized property records and available lot details.</p></div>
        <a class="btn-primary" href="{{ route('properties.create') }}">Add property</a>
    </div>
    <x-feedback />
    <form method="GET" action="{{ route('properties.index') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white p-5">
        <x-select-field name="location" label="Location" class="min-w-40">
            <option value="">All locations</option>
            @foreach($locations as $location)<option value="{{ $location->value }}" @selected(request('location') === $location->value)>{{ $location->value }}</option>@endforeach
        </x-select-field>
        <button class="btn-primary">Filter properties</button><a class="text-link px-2 py-3 text-sm" href="{{ route('properties.index') }}">Clear</a>
    </form>
    <div class="grid gap-4 sm:grid-cols-2">
        @forelse($properties as $property)
            <article class="min-w-0 rounded-2xl border border-stone-200 bg-white p-6">
                <span class="eyebrow">{{ $property->location }}</span><h2 class="mt-3 break-words text-xl font-semibold text-emerald-950"><a class="text-link" href="{{ route('properties.show', $property) }}">{{ $property->name }}</a></h2>
                <p class="mt-2 break-words text-sm text-stone-500">{{ $property->address ?? 'Address not recorded' }}</p>
                <div class="mt-6 flex items-center justify-between gap-3 border-t border-stone-100 pt-4"><p class="text-sm text-stone-600">{{ $property->lots_count }} {{ Str::plural('lot', $property->lots_count) }}</p><a href="{{ route('properties.show', $property) }}" class="text-link text-sm">Manage lots →</a></div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-stone-300 p-10 text-center sm:col-span-2"><h2 class="font-semibold text-emerald-950">No properties found</h2><p class="mt-2 text-sm text-stone-500">Add an authorized property or choose another location.</p></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $properties->links() }}</div>
</x-workspace>
