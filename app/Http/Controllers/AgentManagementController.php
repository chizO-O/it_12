<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\UniqueUserEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\EmployeeActivationCode;
use Illuminate\View\View;

class AgentManagementController extends Controller
{
    public function index(Request $request): View
    {
        return view('agents.index', [
            'agents' => User::where('role', UserRole::Agent->value)
                ->where('unit_manager_id', $request->user()->id)
                ->orderBy('name')->paginate(15),
            'unmanagedAgents' => User::where('role', UserRole::Agent->value)
                ->whereNull('unit_manager_id')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new UniqueUserEmail],
        ]);
        $agent = new User(['name' => $data['name'], 'email' => $data['email']]);
        $agent->role = UserRole::Agent;
        $agent->unit_manager_id = $request->user()->id;
        $agent->invited_by_user_id = $request->user()->id;
        $agent->save();
        return $this->issueCode($agent);
    }

    public function invite(Request $request, User $agent): RedirectResponse
    {
        abort_unless($agent->role === UserRole::Agent && $agent->unit_manager_id === $request->user()->id && $agent->password === null, 403);
        if ($agent->invited_by_user_id === null) {
            $agent->invited_by_user_id = $request->user()->id;
            $agent->save();
        }

        return $this->issueCode($agent);
    }

    public function claim(Request $request, User $agent): RedirectResponse
    {
        DB::transaction(function () use ($request, $agent) {
            $locked = User::whereKey($agent->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->role === UserRole::Agent && $locked->unit_manager_id === null, 403);
            $locked->unit_manager_id = $request->user()->id;
            $locked->save();
        });
        return to_route('agents.index')->with('status', 'Existing Agent linked to your management account.');
    }

    private function issueCode(User $agent): RedirectResponse
    {
        return to_route('agents.index')
            ->with('status', 'Agent saved. Share this one-time setup code privately (valid for 60 minutes).')
            ->with('activation_code', EmployeeActivationCode::issue($agent))
            ->with('activation_email', $agent->email);
    }
}
