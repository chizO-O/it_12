<?php

namespace App\Http\Controllers;

use App\Enums\PropertyLocation;
use App\Http\Requests\SavePropertyRequest;
use App\Models\Property;
use App\Support\LotGrid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Property::class);
        $filters = $request->validate(['location' => ['nullable', Rule::enum(PropertyLocation::class)]]);
        $properties = Property::query()->withCount('lots')
            ->when($filters['location'] ?? null, fn ($query, $location) => $query->where('location', $location))
            ->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();

        return view('properties.index', ['properties' => $properties, 'locations' => PropertyLocation::cases()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Property::class);

        return view('properties.form', ['property' => new Property, 'locations' => PropertyLocation::cases()]);
    }

    public function store(SavePropertyRequest $request): RedirectResponse
    {
        $property = Property::create($request->validated());

        return to_route('properties.show', $property)->with('status', 'Property created. You can now add its lots.');
    }

    public function show(Property $property): View
    {
        Gate::authorize('viewAny', Property::class);
        $property->loadCount('lots');
        $lots = $property->lots()->orderByRaw("COALESCE(block_no, '')")->orderBy('lot_no')->orderBy('id')->paginate(20);

        $grid = LotGrid::build($property->lots()->select(['id', 'block_no', 'lot_no', 'grid_row', 'grid_column'])
            ->orderByRaw("COALESCE(block_no, '')")->orderBy('lot_no')->orderBy('id')->get());

        return view('properties.show', compact('property', 'lots', 'grid'));
    }

    public function edit(Property $property): View
    {
        Gate::authorize('update', $property);

        return view('properties.form', ['property' => $property, 'locations' => PropertyLocation::cases()]);
    }

    public function update(SavePropertyRequest $request, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($request, $property) {
            $current = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $current);
            $current->update($request->validated());
        });

        return to_route('properties.show', $property)->with('status', 'Property updated.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        Gate::authorize('delete', $property);
        DB::transaction(function () use ($property) {
            $current = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            if ($current->lots()->exists()) {
                throw ValidationException::withMessages(['property' => 'A property with lots cannot be deleted. Review and remove its available lots first.']);
            }
            $current->delete();
        });

        return to_route('properties.index')->with('status', 'Property deleted.');
    }
}
