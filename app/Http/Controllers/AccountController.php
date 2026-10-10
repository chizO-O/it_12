<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $input = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $request->user()->update(['name' => $input['name']]);

        return redirect()->route('account')->with('status', 'Your profile has been updated.');
    }
}
