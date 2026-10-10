@props(['property', 'lots', 'layoutId' => 'lot-layout'])
@php
    $blocks = collect($lots->items())->groupBy(fn ($lot) => $lot->block_no === null ? 'unassigned' : 'block:'.$lot->block_no);
@endphp
<section class="lot-layout overflow-hidden rounded-2xl border border-stone-200 bg-white" aria-labelledby="{{ $layoutId }}-heading" data-lot-layout>
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 px-5 py-4">
        <div>
            <h2 id="{{ $layoutId }}-heading" class="font-semibold text-emerald-950">Schematic lot layout</h2>
            <p class="mt-1 text-xs text-stone-500">Select a parcel for details. Scroll to explore the layout.</p>
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
        <div class="lot-layout-viewport overflow-auto" data-layout-viewport tabindex="0" aria-label="Scrollable schematic lot layout">
            <div class="lot-layout-canvas" data-layout-canvas>
                @foreach($blocks as $blockLots)
                    @php
                        $blockLots = $blockLots->values();
                        $columns = min(12, max(6, (int) ceil($blockLots->count() / 2)));
                        $rows = (int) ceil($blockLots->count() / $columns);
                        $height = 240 + $rows * 126;
                        $width = 844 / $columns;
                        $blockLabel = $blockLots->first()->block_no === null ? 'Lots without a block number' : 'Block '.$blockLots->first()->block_no;
                        $blockId = $layoutId.'-block-'.$loop->index;
                    @endphp
                    <div class="lot-layout-block">
                        <h3 id="{{ $blockId }}" class="break-words px-6 pt-5 text-sm font-semibold text-emerald-950">{{ $blockLabel }}</h3>
                        <svg viewBox="0 0 1000 {{ $height }}" class="block w-full" role="group" aria-labelledby="{{ $blockId }}">
                            <g aria-hidden="true">
                                <rect width="1000" height="{{ $height }}" fill="#d9ddbd" />
                                <path d="M-20 54 H1020 M-20 {{ $height - 54 }} H1020" fill="none" stroke="#83917d" stroke-width="44" />
                                <path d="M-20 54 H1020 M-20 {{ $height - 54 }} H1020" fill="none" stroke="#bcc0b5" stroke-width="36" />
                                <rect x="40" y="100" width="920" height="{{ $rows * 126 + 40 }}" rx="28" fill="none" stroke="#647761" stroke-width="48" />
                                <rect x="40" y="100" width="920" height="{{ $rows * 126 + 40 }}" rx="28" fill="none" stroke="#bcc0b5" stroke-width="38" />
                                <rect x="65" y="117" width="872" height="{{ $rows * 126 + 6 }}" rx="14" fill="#c5cba5" stroke="#344c36" stroke-width="4" />
                                @foreach([90, 140, 190, 810, 860, 910] as $treeX)
                                    <circle cx="{{ $treeX }}" cy="15" r="11" fill="#6d8d57" /><circle cx="{{ $treeX - 3 }}" cy="12" r="7" fill="#8ca76c" />
                                    <circle cx="{{ $treeX }}" cy="{{ $height - 15 }}" r="11" fill="#6d8d57" /><circle cx="{{ $treeX - 3 }}" cy="{{ $height - 18 }}" r="7" fill="#8ca76c" />
                                @endforeach
                                <text x="500" y="58" text-anchor="middle" class="layout-lane-label">ILLUSTRATIVE LANE</text>
                                <text x="500" y="{{ $height - 50 }}" text-anchor="middle" class="layout-lane-label">ILLUSTRATIVE LANE</text>
                            </g>
                            @foreach($blockLots as $lot)
                                @php
                                    $column = $loop->index % $columns;
                                    $row = intdiv($loop->index, $columns);
                                    $x = 78 + $column * $width;
                                    $y = 120 + $row * 126;
                                    $slant = $row % 2 === 0 ? 16 : -16;
                                    $topX = $x + max($slant, 0);
                                    $bottomX = $x + max(-$slant, 0);
                                    $points = $topX.','.$y.' '.($topX + $width).','.$y.' '.($bottomX + $width).','.($y + 126).' '.$bottomX.','.($y + 126);
                                    $centerX = $x + $width / 2 + 8;
                                    $centerY = $y + 63;
                                    $labelId = $layoutId.'-label-'.$lot->id;
                                @endphp
                                <a href="{{ route('inventory.lot', [$property, $lot]) }}" class="lot-parcel" data-parcel-status="{{ $lot->status->value }}" data-lot-dialog="lot-details-{{ $lot->id }}" aria-haspopup="dialog" aria-label="Lot {{ $lot->lot_no }}, {{ $blockLabel }}, {{ $lot->area_sqm }} square meters, {{ $lot->status->label() }}, PHP {{ $lot->selling_price }}" tabindex="0">
                                    <title>Lot {{ $lot->lot_no }} · {{ $lot->area_sqm }} m² · {{ $lot->status->label() }} · ₱{{ number_format((float) $lot->selling_price, 2) }}</title>
                                    <polygon points="{{ $points }}" class="lot-parcel-shape" />
                                    <clipPath id="{{ $labelId }}"><polygon points="{{ $points }}" /></clipPath>
                                    <g clip-path="url(#{{ $labelId }})" aria-hidden="true" class="lot-parcel-label">
                                        <text transform="rotate(-78 {{ $centerX }} {{ $centerY }})" x="{{ $centerX }}" y="{{ $centerY - 3 }}" text-anchor="middle" class="parcel-number">Lot {{ Str::limit($lot->lot_no, 18) }}</text>
                                        <text transform="rotate(-78 {{ $centerX }} {{ $centerY }})" x="{{ $centerX }}" y="{{ $centerY + 14 }}" text-anchor="middle" class="parcel-area">{{ number_format((float) $lot->area_sqm, 2) }} m²</text>
                                    </g>
                                </a>
                            @endforeach
                        </svg>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-stone-50 px-6 py-12 text-center"><h3 class="font-semibold text-emerald-950">No lots with this status</h3><p class="mt-2 text-sm text-stone-500">Choose another status or add an authorized lot to see its parcel.</p></div>
    @endif
    <p class="border-t border-stone-200 bg-stone-50 px-5 py-3 text-xs leading-5 text-stone-500">Schematic only. Parcel shapes, sizes, lanes, and positions do not represent a surveyed subdivision plan. This view shows the lots on the current page.</p>
</section>
