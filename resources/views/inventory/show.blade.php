<x-workspace :title="$property->name.' · Lots'">
    <a href="{{ route('inventory.index', ['location' => $property->location]) }}" class="text-link text-sm">← Properties in {{ $property->location }}</a>
    <div class="mb-7 mt-6 flex flex-wrap items-end justify-between gap-4"><div class="min-w-0"><p class="eyebrow">{{ $property->location }}</p><h1 class="mt-2 break-words text-3xl font-semibold text-emerald-950">{{ $property->name }}</h1><p class="mt-2 break-words text-sm text-stone-500">{{ $property->address ?? 'Address not recorded' }}</p></div>@can('update', $property)<a href="{{ route('properties.show', $property) }}" class="btn-secondary">Manage property</a>@endcan</div>
    <x-feedback />
    <ul class="mb-6 flex flex-wrap gap-3" aria-label="Lot status totals">@foreach($statuses as $lotStatus)<li class="flex items-center gap-2"><x-lot-status :status="$lotStatus" /><span class="text-sm font-medium text-stone-600">{{ $counts[$lotStatus->value] ?? 0 }}</span></li>@endforeach</ul>
    <form method="GET" action="{{ route('inventory.show', $property) }}" class="mb-6 flex flex-wrap items-end gap-3">
        <x-select-field name="status" label="Lot status" class="min-w-48">
            <option value="all" @selected($status === 'all')>All statuses</option>@foreach($statuses as $lotStatus)<option value="{{ $lotStatus->value }}" @selected($status === $lotStatus->value)>{{ $lotStatus->label() }}</option>@endforeach
        </x-select-field><button class="btn-secondary">Show lots</button><span class="px-2 py-3 text-sm text-stone-500">{{ $lots->total() }} {{ Str::plural('lot', $lots->total()) }} found</span>
    </form>
    <x-lot-layout :property="$property" :lots="$lots" :grid="$grid" layout-id="inventory-layout" />
    <div class="mt-6">{{ $lots->links() }}</div>
    <x-lot-dialogs :property="$property" :lots="$lots" />
</x-workspace>
