<?php

namespace App\Http\Requests;

use App\Models\Lot;
use App\Rules\UniqueLotNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lot = $this->route('lot');

        return $lot
            ? $this->user()->can('update', $lot)
            : $this->user()->can('create', Lot::class);
    }

    public function rules(): array
    {
        return [
            'lot_no' => ['required', 'string', 'max:255', new UniqueLotNumber($this->route('property')->id, $this->input('block_no'), $this->route('lot')?->id)],
            'block_no' => ['nullable', 'string', 'max:255'],
            'area_sqm' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999.99'],
            'grid_row' => ['nullable', 'required_with:grid_column', 'integer', 'between:1,40'],
            'grid_column' => ['nullable', 'required_with:grid_row', 'integer', 'between:1,12'],
            'property_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('grid_row') || ! $this->filled('grid_column')) {
                return;
            }

            $normalizedBlock = mb_strtolower(trim($this->input('block_no') ?? ''));
            $exists = Lot::query()
                ->where('property_id', $this->route('property')->id)
                ->where('grid_row', $this->integer('grid_row'))
                ->where('grid_column', $this->integer('grid_column'))
                ->whereRaw("COALESCE(LOWER(TRIM(block_no)), '') = ?", [$normalizedBlock])
                ->when($this->route('lot'), fn ($query, $lot) => $query->whereKeyNot($lot->id))
                ->exists();

            if ($exists) {
                $validator->errors()->add('grid_row', 'This position is already used by another lot in the same block.');
            }
        });
    }
}
