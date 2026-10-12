<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\UniqueUserEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = strtolower(trim($input['email']));
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new UniqueUserEmail],
            'password' => ['required', 'string', Password::min(12), 'confirmed'],
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $user->role = UserRole::Buyer;
        // Backward-compatibility activation flag: no email ownership check is performed.
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
