<?php

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Enums\PropertyLocation;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'location' => ['nullable', Rule::enum(PropertyLocation::class)],
            'availability' => ['nullable', Rule::in(['available', 'all'])],
        ]);
        $availability = $filters['availability'] ?? 'available';
        $properties = Property::query()->withCount(['lots', 'lots as available_lots_count' => fn ($query) => $query->where('status', LotStatus::Available->value)])
            ->when($filters['location'] ?? null, fn ($query, $location) => $query->where('location', $location))
            ->when($availability === 'available', fn ($query) => $query->whereHas('lots', fn ($lots) => $lots->where('status', LotStatus::Available->value)))
            ->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();

        return view('inventory.index', ['properties' => $properties, 'locations' => PropertyLocation::cases(), 'availability' => $availability]);
    }

    public function show(Request $request, Property $property): View
    {
        Gate::authorize('view', $property);
        $filters = $request->validate(['status' => ['nullable', Rule::in([...array_column(LotStatus::cases(), 'value'), 'all'])]]);
        $status = $filters['status'] ?? 'available';
        $lots = $property->lots()->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByRaw("COALESCE(block_no, '')")->orderBy('lot_no')->orderBy('id')->paginate(24)->withQueryString();
        $counts = $property->lots()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('inventory.show', ['property' => $property, 'lots' => $lots, 'status' => $status, 'statuses' => LotStatus::cases(), 'counts' => $counts]);
    }

    public function lot(Property $property, Lot $lot): View
    {
        Gate::authorize('view', $lot);

        return view('inventory.lot', compact('property', 'lot'));
    }
}
