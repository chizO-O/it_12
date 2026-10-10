<?php

namespace App\Http\Requests;

use App\Enums\PropertyLocation;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property
            ? $this->user()->can('update', $property)
            : $this->user()->can('create', Property::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', Rule::enum(PropertyLocation::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'total_area_sqm' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
