<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->dashboardRoute());
    }

    public function show(Request $request): View
    {
        $role = $request->user()->role;

        return view('dashboard', [
            'workspace' => $role->workspaceLabel(),
            'modules' => config('workspace.navigation.'.$role->value, []),
        ]);
    }
}
