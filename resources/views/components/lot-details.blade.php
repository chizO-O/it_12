@props(['property', 'lot'])
<div class="space-y-6">
    <x-lot-status :status="$lot->status" />
    <dl class="grid grid-cols-2 gap-x-5 gap-y-6 text-sm">
        <div class="col-span-2"><dt class="text-stone-500">Property</dt><dd class="mt-1 break-words font-semibold text-emerald-950">{{ $property->name }}</dd></div>
        <div><dt class="text-stone-500">Lot number</dt><dd class="mt-1 break-words font-semibold">{{ $lot->lot_no }}</dd></div>
        <div><dt class="text-stone-500">Block number</dt><dd class="mt-1 break-words font-semibold">{{ $lot->block_no ?? 'Not specified' }}</dd></div>
        <div><dt class="text-stone-500">Area</dt><dd class="mt-1 font-semibold">{{ number_format((float) $lot->area_sqm, 2) }} m²</dd></div>
        <div><dt class="text-stone-500">Location</dt><dd class="mt-1 font-semibold">{{ $property->location }}</dd></div>
        <div class="col-span-2 rounded-xl bg-stone-50 p-4"><dt class="text-stone-500">Selling price</dt><dd class="mt-1 break-words text-2xl font-semibold text-emerald-950">₱{{ number_format((float) $lot->selling_price, 2) }}</dd></div>
        @if($property->address)<div class="col-span-2"><dt class="text-stone-500">Address</dt><dd class="mt-1 break-words">{{ $property->address }}</dd></div>@endif
    </dl>
    @if($property->description)<p class="whitespace-pre-line break-words text-sm leading-6 text-stone-600">{{ $property->description }}</p>@endif
    <p class="text-xs leading-5 text-stone-500">Availability shown as of this page load. Confirm current availability with an Agent.</p>
</div>
