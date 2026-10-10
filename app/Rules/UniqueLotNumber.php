<?php

namespace App\Rules;

use App\Models\Lot;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueLotNumber implements ValidationRule
{
    public function __construct(
        private int $propertyId,
        private mixed $blockNumber,
        private ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || (! is_string($this->blockNumber) && $this->blockNumber !== null)) {
            return;
        }

        $exists = Lot::where('property_id', $this->propertyId)
            ->whereRaw('LOWER(TRIM(lot_no)) = LOWER(TRIM(?))', [$value])
            ->whereRaw("COALESCE(LOWER(TRIM(block_no)), '') = LOWER(TRIM(?))", [$this->blockNumber ?? ''])
            ->when($this->ignoreId, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('This lot number already exists in the same property and block.');
        }
    }
}
