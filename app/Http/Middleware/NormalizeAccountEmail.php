<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeAccountEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->routeIs('login.store', 'register.store', 'password.email', 'password.update', 'employees.store')) {
            $request->validate(['email' => ['sometimes', 'nullable', 'string']]);

            if (is_string($request->input('email'))) {
                $request->merge(['email' => strtolower(trim($request->input('email')))]);
            }
        }

        return $next($request);
    }
}
