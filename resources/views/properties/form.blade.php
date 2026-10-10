@php($editing = $property->exists)
<x-workspace :title="$editing ? 'Edit property' : 'Add property'">
    <a href="{{ $editing ? route('properties.show', $property) : route('properties.index') }}" class="text-link text-sm">← Back to properties</a>
    <p class="eyebrow mt-6">Field Planner</p><h1 class="mb-6 mt-2 text-3xl font-semibold text-emerald-950">{{ $editing ? 'Edit property' : 'Add property' }}</h1>
    <x-feedback />
    <form method="POST" action="{{ $editing ? route('properties.update', $property) : route('properties.store') }}" class="max-w-2xl space-y-6 rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
        @csrf
        @if($editing) @method('PUT') @endif
        <x-form-field name="name" label="Property name" :value="$property->name" maxlength="255" />
        <x-select-field name="location" label="Location" :required="true">
            <option value="">Choose a location</option>
            @foreach($locations as $location)<option value="{{ $location->value }}" @selected(old('location', $property->location) === $location->value)>{{ $location->value }}</option>@endforeach
        </x-select-field>
        <x-form-field name="address" label="Address (optional)" :value="$property->address" :required="false" maxlength="255" />
        <x-form-field name="total_area_sqm" label="Total area in m² (optional)" type="number" :value="$property->total_area_sqm" :required="false" min="0.01" max="9999999999.99" step="0.01" />
        <div><label for="description" class="mb-2 block text-sm font-medium text-stone-700">Description (optional)</label>
            @php($description = old('description', $property->description))
            <textarea id="description" name="description" rows="5" maxlength="10000" class="form-input" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ is_string($description) ? $description : '' }}</textarea>
            @error('description')<p id="description-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary">{{ $editing ? 'Save property' : 'Create property' }}</button>
    </form>
</x-workspace>
