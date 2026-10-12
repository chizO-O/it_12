<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeActivationController extends Controller
{
    public function create(): View
    {
        return view('auth.activate-employee');
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'min:32', 'max:255'],
            'password' => ['required', 'string', PasswordRule::min(12), 'confirmed'],
        ]);

        DB::transaction(function () use ($data): void {
            $employee = User::query()->whereRaw('LOWER(email) = ?', [$data['email']])
                ->lockForUpdate()->first();

            // Never disclose whether an account exists or what role it has.
            if (! $employee || $employee->invited_by_user_id === null
                || $employee->role === UserRole::Buyer || $employee->password !== null
                || ! Password::broker()->tokenExists($employee, $data['code'])) {
                throw ValidationException::withMessages([
                    'code' => 'The account, setup code, or eligibility is invalid or has expired.',
                ]);
            }

            // Laravel's 'hashed' cast securely hashes this password.
            $employee->password = $data['password'];
            $employee->email_verified_at = now(); // activation marker, not mailbox verification
            $employee->save();
            Password::broker()->deleteToken($employee); // single-use
        });

        return to_route('login')->with('status', 'Account activated. You can now sign in with your password.');
    }
}
