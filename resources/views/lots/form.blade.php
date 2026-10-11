@php($editing = $lot->exists)
<x-workspace :title="$editing ? 'Edit lot' : 'Add lot'">
    <a class="text-link text-sm" href="{{ route('properties.show', $property) }}">← Back to property</a>
    <p class="eyebrow mt-6">{{ $property->location }}</p><h1 class="mb-2 mt-2 text-3xl font-semibold text-emerald-950">{{ $editing ? 'Edit lot' : 'Add lot' }}</h1><p class="mb-6 break-words text-stone-600">{{ $property->name }}</p>
    <x-feedback />
    <form method="POST" action="{{ $editing ? route('properties.lots.update', [$property, $lot]) : route('properties.lots.store', $property) }}" class="max-w-2xl space-y-6 rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="grid gap-6 sm:grid-cols-2"><x-form-field name="lot_no" label="Lot number" :value="$lot->lot_no" maxlength="255" /><x-form-field name="block_no" label="Block number (optional)" :value="$lot->block_no" :required="false" maxlength="255" /></div>
        <div class="grid gap-6 sm:grid-cols-2"><x-form-field name="area_sqm" label="Area in m²" type="number" :value="$lot->area_sqm" min="0.01" max="9999999999.99" step="0.01" /><x-form-field name="selling_price" label="Selling price (PHP)" type="number" :value="$lot->selling_price" min="0.01" max="9999999999999.99" step="0.01" /></div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900">
            <p class="font-semibold">Automatic lot placement</p>
            <p>GroveMap groups lots by block and places them in natural lot-number order (Lot 1, Lot 2, Lot 3 … Lot 10). No map row or column is needed. The drawing is illustrative, not a surveyed location.</p>
        </div>
        <div class="rounded-xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-900"><p class="font-semibold">Status: Available</p><p>New lots start as Available. Reservations and payment records control later status changes.</p></div>
        <button class="btn-primary">{{ $editing ? 'Save lot' : 'Create lot' }}</button>
    </form>
</x-workspace>
