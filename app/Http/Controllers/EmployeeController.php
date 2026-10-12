<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\UniqueUserEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\EmployeeActivationCode;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View
    {
        return view('employees.index', [
            'employees' => User::whereIn('role', [UserRole::Admin->value, UserRole::UnitManager->value, UserRole::FieldPlanner->value, UserRole::Cashier->value])->latest()->paginate(15),
            'roles' => self::officeRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new UniqueUserEmail],
            'role' => ['required', Rule::in(array_column(self::officeRoles(), 'value'))],
        ]);

        $employee = new User(['name' => $input['name'], 'email' => $input['email']]);
        $employee->role = UserRole::from($input['role']);
        $employee->invited_by_user_id = $request->user()->id;
        $employee->save();

        return $this->issueCode($employee);
    }

    public function invite(User $employee): RedirectResponse
    {
        abort_if(in_array($employee->role, [UserRole::Buyer, UserRole::Agent], true) || $employee->password !== null, 403);

        if ($employee->invited_by_user_id === null) {
            $employee->invited_by_user_id = auth()->id();
            $employee->save();
        }

        return $this->issueCode($employee);
    }

    public function updateRole(Request $request, User $employee): RedirectResponse
    {
        // Keep public Buyers outside employee administration and retain the acting Admin.
        abort_if(in_array($employee->role, [UserRole::Buyer, UserRole::Agent], true) || $employee->is($request->user()), 403);

        $input = $request->validate([
            'role' => ['required', Rule::in(array_column(self::officeRoles(), 'value'))],
        ]);

        $employee->role = UserRole::from($input['role']);
        $employee->save();

        return redirect()->route('employees.index')->with('status', 'The employee role has been updated.');
    }

    private static function officeRoles(): array
    {
        return [UserRole::Admin, UserRole::UnitManager, UserRole::FieldPlanner, UserRole::Cashier];
    }

    private function issueCode(User $employee): RedirectResponse
    {
        return to_route('employees.index')
            ->with('status', 'Account saved. Give the employee this one-time setup code privately (valid for 60 minutes).')
            ->with('activation_code', EmployeeActivationCode::issue($employee))
            ->with('activation_email', $employee->email);
    }
}
