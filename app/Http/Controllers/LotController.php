<?php

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Http\Requests\SaveLotRequest;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LotController extends Controller
{
    public function create(Property $property): View
    {
        Gate::authorize('create', Lot::class);

        return view('lots.form', ['property' => $property, 'lot' => new Lot]);
    }

    public function store(SaveLotRequest $request, Property $property): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $property) {
                $current = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
                $lot = $current->lots()->make($request->safe()->only(['lot_no', 'block_no', 'area_sqm', 'selling_price', 'grid_row', 'grid_column']));
                $lot->status = LotStatus::Available;
                $lot->save();
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(
                str_contains($exception->getMessage(), 'lots_layout_position_unique')
                    ? ['grid_row' => 'This grid position is already assigned to another lot in the same block.']
                    : ['lot_no' => 'This lot number already exists in the same property and block.']
            );
        }

        return to_route('properties.show', $property)->with('status', 'Lot added as Available.');
    }

    public function edit(Property $property, Lot $lot): View
    {
        Gate::authorize('update', $lot);

        return view('lots.form', compact('property', 'lot'));
    }

    public function update(SaveLotRequest $request, Property $property, Lot $lot): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $property, $lot) {
                $current = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
                $currentLot = $current->lots()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $currentLot);
                $currentLot->update($request->safe()->only(['lot_no', 'block_no', 'area_sqm', 'selling_price', 'grid_row', 'grid_column']));
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(
                str_contains($exception->getMessage(), 'lots_layout_position_unique')
                    ? ['grid_row' => 'This grid position is already assigned to another lot in the same block.']
                    : ['lot_no' => 'This lot number already exists in the same property and block.']
            );
        }

        return to_route('properties.show', $property)->with('status', 'Lot updated.');
    }

    public function destroy(Property $property, Lot $lot): RedirectResponse
    {
        Gate::authorize('delete', $lot);
        DB::transaction(function () use ($property, $lot) {
            $current = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            $currentLot = $current->lots()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('delete', $currentLot);
            if ($currentLot->inquiries()->exists() || $currentLot->reservations()->exists()) {
                throw ValidationException::withMessages(['lot' => 'This lot has an inquiry or reservation history and cannot be deleted.']);
            }
            $currentLot->delete();
        });

        return to_route('properties.show', $property)->with('status', 'Lot deleted.');
    }
}
