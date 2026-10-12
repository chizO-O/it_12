<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Password;

final class EmployeeActivationCode
{
    /**
     * Uses Laravel's existing password-reset token repository (hashed, expiring,
     * single-use), so no plaintext setup codes are stored in Supabase.
     */
    public static function issue(User $employee): string
    {
        if ($employee->password !== null || $employee->invited_by_user_id === null) {
            throw new \LogicException('Only pending staff invitations can receive a setup code.');
        }

        // A newly issued token replaces any previous one for the same email.
        return Password::broker()->createToken($employee);
    }
}
