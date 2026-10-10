<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueUserEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && User::whereRaw('LOWER(email) = ?', [strtolower(trim($value))])->exists()) {
            $fail('validation.unique')->translate();
        }
    }
}
