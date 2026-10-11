@props(['property', 'lots', 'grid', 'layoutId' => 'lot-layout'])
@php
    // One neighborhood shared by ALL blocks. The whole property is used for
    // placement; pagination / status filters only determine which lot links
    // appear on this page, so a lot never shifts when a filter is changed.
    $neighborhood = \App\Support\NeighborhoodMap::build($grid);
    $visibleLots = collect($lots->items());
    $blockLabels = $visibleLots->mapWithKeys(fn ($lot) => [
        \App\Support\LotGrid::blockKey($lot->block_no) => trim($lot->block_no ?? '') === ''
            ? 'Lots without a block number'
            : 'Block '.$lot->block_no,
    ])->all();
@endphp
<section class="lot-layout overflow-hidden rounded-2xl border border-stone-200 bg-white" aria-labelledby="{{ $layoutId }}-heading" data-lot-layout>
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 px-5 py-4">
        <div>
            <h2 id="{{ $layoutId }}-heading" class="font-semibold text-emerald-950">Schematic lot layout</h2>
            <p class="mt-1 text-xs text-stone-500">Select a parcel for details. Scroll to explore the compact neighborhood.</p>
        </div>
        <div class="hidden items-center gap-1 rounded-xl border border-stone-200 p-1" data-layout-controls>
            <button type="button" class="layout-control" data-layout-zoom="out" aria-label="Zoom out">−</button>
            <button type="button" class="layout-control min-w-16 text-xs" data-layout-zoom="reset" aria-label="Reset layout zoom"><span data-layout-zoom-label>100%</span></button>
            <button type="button" class="layout-control" data-layout-zoom="in" aria-label="Zoom in">+</button>
        </div>
    </div>
    <div class="flex flex-wrap gap-x-5 gap-y-2 border-b border-stone-200 px-5 py-3 text-xs text-stone-600" aria-label="Parcel status legend">
        @foreach(\App\Enums\LotStatus::cases() as $parcelStatus)
            <span class="inline-flex items-center gap-2"><span class="parcel-swatch" data-parcel-status="{{ $parcelStatus->value }}" aria-hidden="true"></span>{{ $parcelStatus->label() }}</span>
        @endforeach
    </div>
    @if($lots->count())
        <div class="lot-layout-viewport overflow-auto" data-layout-viewport tabindex="0" aria-label="Scrollable single-neighborhood schematic lot layout">
            <div class="lot-layout-canvas" data-layout-canvas>
                <svg class="mx-auto block h-auto max-w-full" style="width: {{ $neighborhood['width'] }}px" viewBox="0 0 {{ $neighborhood['width'] }} {{ $neighborhood['height'] }}" role="group" aria-label="One compact stacked schematic neighborhood containing recorded lots" data-neighborhood-map>
                    <rect width="{{ $neighborhood['width'] }}" height="{{ $neighborhood['height'] }}" fill="#dce3c4" />
                    <g aria-hidden="true" class="neighborhood-landscape">
                        <ellipse cx="70" cy="150" rx="85" ry="135" fill="#cbd8ae" />
                        <ellipse cx="{{ $neighborhood['width'] - 80 }}" cy="90" rx="130" ry="150" fill="#c9d9ad" />
                        <ellipse cx="85" cy="{{ $neighborhood['height'] - 110 }}" rx="95" ry="155" fill="#c6d4a9" />
                        <ellipse cx="{{ $neighborhood['width'] - 80 }}" cy="{{ $neighborhood['height'] - 90 }}" rx="145" ry="155" fill="#cbd5b4" />
                    </g>

                    {{-- A single compact neighborhood: rounded lot block, one
                         shared road, next rounded block. No artificial lots. --}}
                    <g class="neighborhood-streets" aria-hidden="true">
                        @foreach($neighborhood['roads'] as $road)
                            <path d="{{ $road['path'] }}" class="neighborhood-road-edge" />
                            <path d="{{ $road['path'] }}" class="neighborhood-road-surface" />
                            <text x="{{ $road['label_x'] }}" y="{{ $road['label_y'] }}" text-anchor="middle" class="layout-lane-label">{{ $road['label'] }}</text>
                        @endforeach
                    </g>

                    <g class="neighborhood-rounded-blocks" aria-hidden="true">
                        @foreach($neighborhood['blocks'] as $blockKey => $block)
                            @php
                                $blockLabel = $blockLabels[$blockKey] ?? ($blockKey === 'unassigned' ? 'Lots without a block number' : 'Block '.substr($blockKey, 6));
                                $rounding = min(74, $block['capsule_height'] / 2);
                            @endphp
                            <rect x="{{ $block['capsule_x'] }}" y="{{ $block['capsule_y'] }}" width="{{ $block['capsule_width'] }}" height="{{ $block['capsule_height'] }}" rx="{{ $rounding }}" class="neighborhood-block-capsule" />
                            <rect x="{{ $block['capsule_x'] + 8 }}" y="{{ $block['capsule_y'] + 8 }}" width="{{ $block['capsule_width'] - 16 }}" height="{{ $block['capsule_height'] - 16 }}" rx="{{ max(12, $rounding - 8) }}" class="neighborhood-block-capsule-inner" />
                            <text x="{{ $block['label_x'] }}" y="{{ $block['label_y'] }}" class="neighborhood-block-name" text-anchor="middle">{{ $blockLabel }}</text>
                        @endforeach
                    </g>

                    @foreach($visibleLots as $lot)
                        @php
                            $blockKey = \App\Support\LotGrid::blockKey($lot->block_no);
                            $position = $grid['positions'][$lot->id] ?? null;
                            $block = $neighborhood['blocks'][$blockKey] ?? null;
                            $parcel = $position && $block ? \App\Support\NeighborhoodMap::parcel($block, $position) : null;
                            $blockLabel = trim($lot->block_no ?? '') === '' ? 'Lots without a block number' : 'Block '.$lot->block_no;
                            $labelId = $layoutId.'-parcel-label-'.$lot->id;
                        @endphp
                        @if($parcel)
                            <a href="{{ $layoutId === 'management-layout' ? route('properties.show', $property) : route('inventory.lot', [$property, $lot]) }}" class="lot-parcel" data-lot-id="{{ $lot->id }}" data-property-id="{{ $property->id }}" data-grid-row="{{ $position['row'] + 1 }}" data-grid-column="{{ $position['column'] + 1 }}" data-auto-position="block-lot" data-parcel-status="{{ $lot->status->value }}" data-lot-dialog="lot-details-{{ $lot->id }}" aria-haspopup="dialog" aria-label="Lot {{ $lot->lot_no }}, {{ $blockLabel }}, {{ $lot->area_sqm }} square meters, {{ $lot->status->label() }}, PHP {{ $lot->selling_price }}" tabindex="0">
                                <title>Lot {{ $lot->lot_no }} · {{ $lot->area_sqm }} m² · {{ $lot->status->label() }} · ₱{{ number_format((float) $lot->selling_price, 2) }}</title>
                                <polygon points="{{ $parcel['points'] }}" class="lot-parcel-shape" />
                                <clipPath id="{{ $labelId }}"><polygon points="{{ $parcel['points'] }}" /></clipPath>
                                <g clip-path="url(#{{ $labelId }})" aria-hidden="true" class="lot-parcel-label">
                                    <text transform="rotate({{ $parcel['angle'] }} {{ $parcel['x'] }} {{ $parcel['y'] }})" x="{{ $parcel['x'] }}" y="{{ $parcel['y'] - 7 }}" text-anchor="middle" class="parcel-number" style="font-size:{{ $parcel['font'] }}px">Lot {{ Str::limit($lot->lot_no, 18) }}</text>
                                    <text transform="rotate({{ $parcel['angle'] }} {{ $parcel['x'] }} {{ $parcel['y'] }})" x="{{ $parcel['x'] }}" y="{{ $parcel['y'] + 10 }}" text-anchor="middle" class="parcel-area" style="font-size:{{ max(8, $parcel['font'] - 1) }}px">{{ number_format((float) $lot->area_sqm, 2) }} m²</text>
                                </g>
                            </a>
                        @endif
                    @endforeach
                    <g aria-hidden="true" class="neighborhood-trees">
                        @foreach($neighborhood['trees'] as $tree)
                            <circle cx="{{ $tree['x'] }}" cy="{{ $tree['y'] }}" r="{{ $tree['r'] }}" fill="#71955e" />
                            <circle cx="{{ $tree['x'] - 3 }}" cy="{{ $tree['y'] - 4 }}" r="{{ $tree['r'] * 0.52 }}" fill="#9bb979" />
                        @endforeach
                    </g>
                </svg>
            </div>
        </div>
    @else
        <div class="bg-stone-50 px-6 py-12 text-center"><h3 class="font-semibold text-emerald-950">No lots with this status</h3><p class="mt-2 text-sm text-stone-500">Choose another status or add an authorized lot to see its parcel.</p></div>
    @endif
    <p class="border-t border-stone-200 bg-stone-50 px-5 py-3 text-xs leading-5 text-stone-500">Schematic only. Parcel shapes, sizes, lanes, and positions do not represent a surveyed subdivision plan. A shared illustrative road separates neighboring rounded blocks; trees decorate the edges. GroveMap automatically groups lots by block and orders them naturally by lot number. Only recorded lots are interactive; pagination and status filters do not alter their assigned schematic cells.</p>
</section>
