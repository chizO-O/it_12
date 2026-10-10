<?php

namespace App\Http\Requests;

use App\Models\Lot;
use App\Rules\UniqueLotNumber;
use Illuminate\Foundation\Http\FormRequest;

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
            'property_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
